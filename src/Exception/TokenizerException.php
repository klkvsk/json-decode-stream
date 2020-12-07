<?php
declare(strict_types=1);

namespace JsonDecodeStream\Exception;

class TokenizerException extends JsonDecodeStreamException
{
    const CODE_UNEXPECTED_TOKEN = 101;
    const CODE_UNEXPECTED_CHAR = 102;
    const CODE_MALFORMED_NUMBER = 201;
    const CODE_MALFORMED_STRING = 202;

    public function __construct($message, int $lineNumber = null, int $charNumber = null, int $code = null)
    {
        if ($lineNumber !== null && $charNumber !== null) {
            $message .= " at $lineNumber:$charNumber";
        }
        parent::__construct($message, $code);
    }

    public static function unexpectedToken(string $token, int $lineNumber = null, int $charNumber = null)
    {
        return new static(
            sprintf('Unexpected token `%s`', $token), $lineNumber, $charNumber, static::CODE_UNEXPECTED_TOKEN
        );
    }

    public static function unexpectedCharacter(string $char, int $lineNumber = null, int $charNumber = null)
    {
        return new static(
            sprintf('Unexpected character `%s`', $char), $lineNumber, $charNumber, static::CODE_UNEXPECTED_CHAR
        );
    }

    public static function malformedNumber(string $number, int $lineNumber = null, int $charNumber = null)
    {
        return new static(
            sprintf('Malformed number `%s`', $number), $lineNumber, $charNumber, static::CODE_MALFORMED_NUMBER
        );
    }

    public static function malformedString(string $string, int $lineNumber = null, int $charNumber = null)
    {
        return new static(
            sprintf('Malformed string `%s`', $string), $lineNumber, $charNumber, static::CODE_MALFORMED_STRING
        );
    }
}
