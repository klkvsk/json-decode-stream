<?php
declare(strict_types=1);

namespace JsonDecodeStream;

use Generator;
use IteratorAggregate;
use JsonDecodeStream\Exception\TokenizerException;
use JsonDecodeStream\Internal\SourceBuffer;

class Tokenizer implements IteratorAggregate
{
    const WHITESPACE_CHARS = " \t\r\n";
    const LITERALS = [
        Token::TRUE => true,
        Token::FALSE => false,
        Token::NUMBER => null,
    ];

    /** @var SourceBuffer */
    protected SourceBuffer $buffer;

    /** @var int|null */
    protected $lineNumber;
    /** @var int|null */
    protected $charNumber;

    public function __construct(SourceBuffer $buffer)
    {
        $this->buffer = $buffer;
    }

    public function getIterator()
    {
        return $this->tokens();
    }

    /**
     * @return Generator|Token[]
     * @throws TokenizerException
     */
    public function tokens(): Generator
    {
        $number = null;
        $string = null;
        $stringSlashes = 0;
        $whitespace = null;
        $specialActual = null;
        $specialParsed = null;

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
                yield $this->createToken(Token::NUMBER, $parsedNumber);
                $number = null;
            }

            if ($string !== null) {
                if ($char == '\\') {
                    $string .= $char;
                    $stringSlashes++;
                    continue;
                } else if ($char == '"' && ($stringSlashes % 2) == 0) {
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

            if ($specialActual !== null) {
                if (strpos($specialActual, $char) !== false) {
                    $specialParsed .= $char;
                } else {
                    throw TokenizerException::unexpectedToken($specialParsed, $this->lineNumber, $this->charNumber);
                }

                if (strlen($specialParsed) == strlen($specialActual)) {
                    if ($specialParsed === $specialActual) {
                        yield $this->createToken($specialActual, static::LITERALS[$specialActual]);
                        $specialActual = null;
                        $specialParsed = null;
                    } else {
                        throw TokenizerException::unexpectedToken($specialParsed, $this->lineNumber, $this->charNumber);
                    }
                }
                continue;
            }

            if (strpos(self::WHITESPACE_CHARS, $char) !== false) {
                $whitespace = $char;
            } else if ($char == "{") {
                yield $this->createToken(Token::OBJECT_START);
            } else if ($char == "}") {
                yield $this->createToken(Token::OBJECT_END);
            } else if ($char == "[") {
                yield $this->createToken(Token::ARRAY_START);
            } else if ($char == "]") {
                yield $this->createToken(Token::ARRAY_END);
            } else if ($char == ":") {
                yield $this->createToken(Token::KEY_DELIMITER);
            } else if ($char == ",") {
                yield $this->createToken(Token::COMA);
            } else if ($char == '"') {
                $string = '';
            } else if (strpos('+-0123456789', $char) !== false) {
                $number = $char;
            } else if ($char == 't') {
                $specialActual = Token::TRUE;
                $specialParsed = $char;
            } else if ($char == 'f') {
                $specialActual = Token::FALSE;
                $specialParsed = $char;
            } else if ($char == 'n') {
                $specialActual = Token::NULL;
                $specialParsed = $char;
            } else {
                throw TokenizerException::unexpectedCharacter($char, $this->lineNumber, $this->charNumber);
            }
        }

        if ($string !== null || $specialParsed != null) {
            throw TokenizerException::malformedString($string ?? $specialParsed, $this->lineNumber, $this->charNumber);
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
        } else if (preg_match('/^[+-]?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][-+]?(0|[1-9][0-9]*))?$/', $number)) {
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