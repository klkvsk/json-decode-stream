<?php
declare(strict_types=1);

namespace JsonDecodeStream\Exception;

use JsonDecodeStream\Token;

class ParserException extends JsonDecodeStreamException
{
    const CODE_INVALID_ARGUMENT = 1;
    const CODE_UNEXPECTED_TOKEN = 101;
    const CODE_EXPECTED_BUT_GOT = 102;

    public function __construct($message, int $code, ?int $lineNumber = null, ?int $charNumber = null)
    {
        if ($lineNumber !== null && $charNumber !== null) {
            $message .= " at $lineNumber:$charNumber";
        }
        parent::__construct($message, $code);
    }

    public static function unexpectedToken(Token $token)
    {
        return new static(
            sprintf('Unexpected token `%s`', $token->getId()),
            static::CODE_UNEXPECTED_TOKEN,
            $token->getLineNumber(), $token->getCharNumber(),
        );
    }

    public static function expectedButGot(string $expected, Token $gotToken)
    {
        $got = ($gotToken->getValue() ? json_encode($gotToken->getValue()) : $gotToken->getId());
        return new static(
            sprintf('Expected `%s` but got `%s`', $expected, $got),
            static::CODE_EXPECTED_BUT_GOT,
            $gotToken->getLineNumber(), $gotToken->getCharNumber(),
        );
    }
}
