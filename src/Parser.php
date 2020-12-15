<?php
declare(strict_types=1);

namespace JsonDecodeStream;

use Generator;
use JsonDecodeStream\Collector\Collector;
use JsonDecodeStream\Collector\CollectorInterface;
use JsonDecodeStream\Exception\ParserException;
use JsonDecodeStream\Internal\SourceBuffer;
use JsonDecodeStream\Internal\Stack;
use JsonDecodeStream\Internal\StackFrame;
use JsonDecodeStream\Source\FileSource;
use JsonDecodeStream\Source\Psr7Source;
use JsonDecodeStream\Source\SourceInterface;
use JsonDecodeStream\Source\StreamSource;
use JsonDecodeStream\Source\StringSource;
use Psr\Http\Message\StreamInterface;

class Parser
{
    /** @var SourceBuffer */
    protected $buffer;

    /** @var Stack */
    protected $stack;

    public function __construct(SourceInterface $source)
    {
        $this->buffer = new SourceBuffer($source);
    }

    public static function fromString(string $string)
    {
        return new static(new StringSource($string));
    }

    public static function fromFile(string $path)
    {
        return new static(new FileSource($path));
    }

    public static function fromStream($stream)
    {
        return new static(new StreamSource($stream));
    }

    public static function fromPsr7(StreamInterface $stream)
    {
        return new static(new Psr7Source($stream));
    }

    /**
     * @param null|string|string[]|CollectorInterface|CollectorInterface[] $selectors
     * single or coma-separated selector string
     * or custom CollectorInterface implementation
     * or array of any of both
     * or null to collect whole documents
     * @param bool                                  $objectsAsAssoc
     * @return iterable|Generator
     * @throws Exception\CollectorException
     * @throws Exception\SelectorException
     * @throws Exception\TokenizerException
     * @throws ParserException
     */
    public function items($selectors = null, bool $objectsAsAssoc = false)
    {
        if (is_string($selectors)) {
            $selectorsArray = explode(',', $selectors);
        } else if (is_array($selectors)) {
            $selectorsArray = $selectors;
        } else if ($selectors instanceof CollectorInterface) {
            $selectorsArray = [ $selectors ];
        } else if ($selectors === null) {
            $selectorsArray = [ null ];
        } else {
            throw new ParserException('Unexpected selectors are provided', ParserException::CODE_INVALID_ARGUMENT);
        }
        $collectors = [];
        foreach ($selectorsArray as $selector) {
            if (is_string($selector) || is_null($selector)) {
                $collectors[] = new Collector($selector, $objectsAsAssoc);
            } elseif ($selector instanceof CollectorInterface) {
                $collectors[] = $selector;
            } else {
                throw new ParserException(
                    'Invalid collector: '
                    . is_object($selector) ? get_class($selector) : gettype($selector),
                    ParserException::CODE_INVALID_ARGUMENT
                );
            }
        }

        foreach ($this->events() as $event) {
            foreach ($collectors as $collector) {
                $yielded = $collector->processEvent($event);
                if (is_array($yielded)) {
                    if (count($yielded) != 2) {
                        throw ParserException::unexpectedCollectorReturn($yielded, $event);
                    }
                    [ $key, $value ] = $yielded;
                    yield $key => $value;
                } else if ($yielded instanceof Generator) {
                    foreach ($yielded as $yieldedSingle) {
                        if (!is_array($yieldedSingle) || count($yieldedSingle) != 2) {
                            throw ParserException::unexpectedCollectorReturn($yielded, $event);
                        }
                        [ $key, $value ] = $yieldedSingle;
                        yield $key => $value;
                    }
                } else if ($yielded === null) {
                    continue;
                } else {
                    throw ParserException::unexpectedCollectorReturn($yielded, $event);
                }
            }
        }
    }

    /**
     * @return Generator|Event[]
     * @psalm-return \Generator<Event>
     * @throws ParserException
     * @noinspection PhpStatementHasEmptyBodyInspection
     */
    public function events(): Generator
    {
        $stack = new Stack();
        $tokens = $this->tokens();

        // shortcut to event factory
        $createEvent = function (string $eventId, $value = null) use ($stack, &$token): Event {
            return $this->createEvent($eventId, $value, $stack, $token->getLineNumber(), $token->getCharNumber());
        };

        foreach ($tokens as $token) {
            if ($token->getId() == Token::WHITESPACE) {
                // ignore whitespaces
                continue;
            }

            if ($stack->isEmpty()) {
                switch ($token->getId()) {
                    case Token::OBJECT_START:
                        yield $createEvent(Event::DOCUMENT_START);
                        yield $createEvent(Event::OBJECT_START);

                        $stack->push(StackFrame::object());
                        break;

                    case Token::ARRAY_START:
                        yield $createEvent(Event::DOCUMENT_START);
                        yield $createEvent(Event::ARRAY_START);

                        $stack->push(StackFrame::array());
                        break;

                    case Token::WHITESPACE:
                    case Token::COMA:
                        // this is ignored at top-level to parse json sequences
                        break;

                    default:
                        throw ParserException::unexpectedToken($token);
                }
                continue;
            }
            if ($stack->current()->isAwaitsComa()) {
                if ($token->getId() == Token::COMA) {
                    $stack->current()->setAwaitsComa(false);
                    if ($stack->current()->isObject()) {
                        $stack->current()->setLastKey(null);
                        $stack->current()->setAwaitsKey(true);
                    }
                    continue;
                } elseif ($stack->current()->isObject() && $token->getId() == Token::OBJECT_END) {
                    // pass
                } elseif ($stack->current()->isArray() && $token->getId() == Token::ARRAY_END) {
                    // pass
                } else {
                    throw ParserException::expectedButGot('","', $token);
                }
            }
            if ($stack->current()->isAwaitsKeyDelimiter()) {
                if ($token->getId() == Token::KEY_DELIMITER) {
                    $stack->current()->setAwaitsKeyDelimiter(false);
                    continue;
                } else {
                    throw ParserException::expectedButGot('":"', $token);
                }
            }
            if ($stack->current()->isAwaitsKey()) {
                if ($token->getId() != Token::STRING && $token->getId() != Token::OBJECT_END) {
                    throw ParserException::expectedButGot('object key', $token);
                }
            }

            if ($stack->current()->isArray()) {
                switch ($token->getId()) {
                    case Token::STRING:
                    case Token::NUMBER:
                    case Token::NULL:
                    case Token::TRUE:
                    case Token::FALSE:
                        $stack->current()->setAwaitsComa(true);
                        $stack->current()->setLastKey(
                            $stack->current()->getElementCount()
                        );
                        $stack->current()->incrementElementCount();
                        yield $createEvent(Event::VALUE, $token->getValue());
                        break;

                    case Token::ARRAY_START:
                        yield $createEvent(Event::ARRAY_START);
                        $stack->current()->setAwaitsComa(true);
                        $stack->current()->setLastKey(
                            $stack->current()->getElementCount()
                        );
                        $stack->current()->incrementElementCount();
                        $stack->push(StackFrame::array());
                        break;

                    case Token::ARRAY_END:
                        $stack->pop();
                        yield $createEvent(Event::ARRAY_END);
                        if ($stack->isEmpty()) {
                            yield $createEvent(Event::DOCUMENT_END);
                        }
                        break;

                    case Token::OBJECT_START:
                        $stack->current()->setLastKey(
                            $stack->current()->getElementCount()
                        );
                        yield $createEvent(Event::OBJECT_START);
                        $stack->current()->setAwaitsComa(true);
                        $stack->current()->incrementElementCount();
                        $stack->push(StackFrame::object());
                        break;

                    case Token::OBJECT_END:
                        $stack->pop();
                        yield $createEvent(Event::OBJECT_END);
                        break;

                    default:
                        throw ParserException::unexpectedToken($token);
                }

                continue;
            }

            if ($stack->current()->isObject()) {
                switch ($token->getId()) {
                    case Token::STRING:
                        if ($stack->current()->isAwaitsKey()) {
                            yield $createEvent(Event::KEY, $token->getValue());
                            $stack->current()->setLastKey($token->getValue());
                            $stack->current()->setAwaitsKeyDelimiter(true);
                            $stack->current()->setAwaitsKey(false);
                        } else {
                            yield $createEvent(Event::VALUE, $token->getValue());
                            $stack->current()->setAwaitsComa(true);
                            $stack->current()->incrementElementCount();
                        }
                        break;

                    case Token::NUMBER:
                    case Token::NULL:
                    case Token::TRUE:
                    case Token::FALSE:
                        yield $createEvent(Event::VALUE, $token->getValue());
                        $stack->current()->setAwaitsComa(true);
                        $stack->current()->incrementElementCount();
                        break;

                    case Token::ARRAY_START:
                        yield $createEvent(Event::ARRAY_START);
                        $stack->current()->setAwaitsComa(true);
                        $stack->current()->incrementElementCount();
                        $stack->push(StackFrame::array());
                        break;

                    case Token::ARRAY_END:
                        yield $createEvent(Event::ARRAY_END);
                        $stack->pop();
                        break;

                    case Token::OBJECT_START:
                        yield $createEvent(Event::OBJECT_START);
                        $stack->current()->setAwaitsComa(true);
                        $stack->current()->incrementElementCount();
                        $stack->push(StackFrame::object());
                        break;

                    case Token::OBJECT_END:
                        $stack->pop();
                        yield $createEvent(Event::OBJECT_END);
                        if ($stack->isEmpty()) {
                            yield $createEvent(Event::DOCUMENT_END);
                        }
                        break;

                    default:
                        throw ParserException::unexpectedToken($token);
                }

                continue;
            }
        }
    }

    /**
     * @return iterable|Tokenizer|Token[]
     */
    public function tokens(): iterable
    {
        return new Tokenizer($this->buffer);
    }

    /**
     * @param string $eventId
     * @param        $value
     * @param Stack  $stack
     * @param int    $lineNumber
     * @param int    $charNumber
     * @return Event
     */
    protected function createEvent(string $eventId, $value, Stack $stack, int $lineNumber, int $charNumber): Event
    {
        return new Event($eventId, $value, $stack, $lineNumber, $charNumber);
    }
}
