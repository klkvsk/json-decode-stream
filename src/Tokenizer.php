<?php
declare(strict_types=1);

namespace JsonDecodeStream;

use Generator;
use IteratorAggregate;
use JsonDecodeStream\Exception\TokenizerException;
use JsonDecodeStream\Internal\SourceBuffer;

class Tokenizer implements IteratorAggregate
{
    protected const WHITESPACE_CHARS = " \t\r\n";

    protected const LITERALS = [
        Token::TRUE  => true,
        Token::FALSE => false,
        Token::NULL  => null,
    ];

    /** @var SourceBuffer|string[] */
    protected $buffer;

    /** @var int|null */
    protected $lineNumber;
    /** @var int|null */
    protected $charNumber;

    public function __construct(SourceBuffer $buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * @return \Traversable|Token[]
     * @psalm-return \Traversable<Token>
     * @throws TokenizerException
     */
    public function getIterator(): \Traversable
    {
        return $this->tokens();
    }

    /**
     * @return Generator|Token[]
     * @psalm-return \Traversable<Token>
     * @throws TokenizerException
     */
    public function tokens()
    {
        /** @var string|null $number collecting chars of numeric token */
        $number = null;
        /** @var string|null $string collecting chars of string token */
        $string = null;
        /** @var int $stringSlashes how many sequentative slashes are found in string */
        $stringSlashes = 0;
        /** @var string|null $whitespace collecting chars of whitespace token */
        $whitespace = null;
        /** @var string|null $literalAwaited holding the string for awaited literal */
        $literalAwaited = null;
        /** @var string|null $literal collecting chars of literal token */
        $literal = null;

        $this->lineNumber = 1;
        $this->charNumber = 1;
        foreach ($this->buffer as $char) {
            if ($char == "\n") {
                $this->lineNumber++;
                $this->charNumber = 1;
            } else {
                $this->charNumber++;
            }

            if ($number !== null) {
                if (strpos('0123456789+-Ee\.', $char) !== false) {
                    $number .= $char;
                    continue;
                }
                $parsedNumber = $this->parseNumber($number);
                $number = null;
                yield $this->createToken(Token::NUMBER, $parsedNumber);
            }

            if ($string !== null) {
                if ($char == '\\') {
                    $string .= $char;
                    $stringSlashes++;
                    continue;
                } elseif ($char == '"' && ($stringSlashes % 2) == 0) {
                    $parsedString = $this->parseString($string);
                    yield $this->createToken(Token::STRING, $parsedString);
                    $string = null;
                    $stringSlashes = 0;
                    continue;
                } else {
                    $string .= $char;
                    $stringSlashes = 0;
                    continue;
                }
            }

            if ($whitespace !== null) {
                if (strpos(static::WHITESPACE_CHARS, $char)) {
                    $whitespace .= $char;
                    continue;
                } else {
                    yield $this->createToken(Token::WHITESPACE, $whitespace);
                    $whitespace = null;
                }
            }

            if ($literalAwaited !== null) {
                if (strpos($literalAwaited, $char) !== false) {
                    $literal .= $char;
                } else {
                    throw TokenizerException::unexpectedToken($literal, $this->lineNumber, $this->charNumber);
                }

                if (strlen($literal) == strlen($literalAwaited)) {
                    if ($literal === $literalAwaited) {
                        yield $this->createToken($literalAwaited, static::LITERALS[$literalAwaited]);
                        $literalAwaited = null;
                        $literal = null;
                    } else {
                        throw TokenizerException::unexpectedToken($literal, $this->lineNumber, $this->charNumber);
                    }
                }
                continue;
            }

            if (strpos(static::WHITESPACE_CHARS, $char) !== false) {
                $whitespace = $char;
            } elseif ($char == "{") {
                yield $this->createToken(Token::OBJECT_START);
            } elseif ($char == "}") {
                yield $this->createToken(Token::OBJECT_END);
            } elseif ($char == "[") {
                yield $this->createToken(Token::ARRAY_START);
            } elseif ($char == "]") {
                yield $this->createToken(Token::ARRAY_END);
            } elseif ($char == ":") {
                yield $this->createToken(Token::KEY_DELIMITER);
            } elseif ($char == ",") {
                yield $this->createToken(Token::COMA);
            } elseif ($char == '"') {
                $string = '';
            } elseif (strpos('+-0123456789', $char) !== false) {
                $number = $char;
            } elseif ($char == 't') {
                $literalAwaited = Token::TRUE;
                $literal = $char;
            } elseif ($char == 'f') {
                $literalAwaited = Token::FALSE;
                $literal = $char;
            } elseif ($char == 'n') {
                $literalAwaited = Token::NULL;
                $literal = $char;
            } else {
                throw TokenizerException::unexpectedCharacter($char, $this->lineNumber, $this->charNumber);
            }
        }

        if ($string !== null || $literal !== null) {
            throw TokenizerException::malformedString($string ?? $literal, $this->lineNumber, $this->charNumber);
        }

        if ($number !== null) {
            $parsedNumber = $this->parseNumber($number);
            yield $this->createToken(Token::NUMBER, $parsedNumber);
            $number = null;
        }

        if ($whitespace !== null) {
            yield $this->createToken(Token::WHITESPACE, $whitespace);
        }
    }

    /**
     * @param string $number
     * @return float|int|null
     * @throws TokenizerException
     */
    protected function parseNumber(string $number)
    {
        if (preg_match_all('/^[+-]?(0|[1-9][0-9]*)$/', $number)) {
            return intval($number);
        } elseif (preg_match('/^[+-]?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][-+]?([0-9]+))?$/', $number)) {
            return doubleval($number);
        } else {
            throw TokenizerException::malformedNumber($number, $this->lineNumber, $this->charNumber);
        }
    }

    /**
     * @param string $string
     * @return string|null
     * @throws TokenizerException
     */
    protected function parseString(string $string)
    {
        $parsed = json_decode('"' . $string . '"');
        if (!is_string($parsed)) {
            throw TokenizerException::malformedString($string, $this->lineNumber, $this->charNumber);
        }
        return $parsed;
    }

    /**
     * @param string $tokenId
     * @param mixed  $value
     * @return Token
     */
    protected function createToken(string $tokenId, $value = null): Token
    {
        return new Token($tokenId, $value, $this->lineNumber, $this->charNumber);
    }
}
