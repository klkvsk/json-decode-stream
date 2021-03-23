<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use JsonDecodeStream\Exception\TokenizerException;
use JsonDecodeStream\Internal\SourceBuffer;
use JsonDecodeStream\Source\FileSource;
use JsonDecodeStream\Source\StringSource;
use JsonDecodeStream\Token;
use JsonDecodeStream\Tokenizer;

class TokenizerTest extends Test
{
    public function doTestAndCollectTokens(string $json)
    {
        $buffer = new SourceBuffer(new StringSource($json));
        $tokenizer = new Tokenizer($buffer);
        $collected = [];
        foreach ($tokenizer->tokens() as $token) {
            $collected []= [ $token->getId() => $token->getValue() ];
        }
        return $collected;
    }

    /**
     * @dataProvider scalars
     * @param $json
     * @param $tokens
     */
    public function testScalar($json, $tokens)
    {
        $collected = $this->doTestAndCollectTokens($json);
        $this->assertSame($tokens, $collected);
    }


    public function scalars()
    {
        return [
            'empty string'      => [ '""', [ [ Token::STRING => '' ] ] ],
            'simple string'     => [ '"foobar"', [ [ Token::STRING => 'foobar' ] ] ],
            'string with quote' => [ '"foo\"bar"', [ [ Token::STRING => 'foo"bar' ] ] ],

            '0'       => [ '0', [ [ Token::NUMBER => 0 ] ] ],
            '1'       => [ '1', [ [ Token::NUMBER => 1 ] ] ],
            '-1'      => [ '-1', [ [ Token::NUMBER => -1 ] ] ],
            '-0'      => [ '-0', [ [ Token::NUMBER => 0 ] ] ],
            '1.23'    => [ '1.23', [ [ Token::NUMBER => 1.23 ] ] ],
            '-1.23'   => [ '-1.23', [ [ Token::NUMBER => -1.23 ] ] ],
            '0.01'    => [ '0.01', [ [ Token::NUMBER => 0.01 ] ] ],
            '-0.01'   => [ '-0.01', [ [ Token::NUMBER => -0.01 ] ] ],
            '1e10'    => [ '1e10', [ [ Token::NUMBER => 1e10 ] ] ],
            '1.23e10' => [ '1.23e10', [ [ Token::NUMBER => 1.23e10 ] ] ],
            '1.23E10' => [ '1.23E10', [ [ Token::NUMBER => 1.23e10 ] ] ],
            '1.23e-2' => [ '1.23e-2', [ [ Token::NUMBER => 1.23e-2 ] ] ],
            '1.23e+2' => [ '1.23e+2', [ [ Token::NUMBER => 1.23e2 ] ] ],
            '1.23e-02' => [ '1.23e-02', [ [ Token::NUMBER => 1.23e-2 ] ] ],
            '1.23e+02' => [ '1.23e+02', [ [ Token::NUMBER => 1.23e2 ] ] ],
            '1.23e02' => [ '1.23e02', [ [ Token::NUMBER => 1.23e2 ] ] ],

            'true'  => [ 'true', [ [ Token::TRUE => true ] ] ],
            'false' => [ 'false', [ [ Token::FALSE => false ] ] ],
            'null'  => [ 'null', [ [ Token::NULL => null ] ] ],

            'coma'   => [ ',', [ [ Token::COMA => null ] ] ],
            'key'    => [ ':', [ [ Token::KEY_DELIMITER => null ] ] ],
            '\\t'    => [ "\t", [ [ Token::WHITESPACE => "\t" ] ] ],
            '\\r'    => [ "\r", [ [ Token::WHITESPACE => "\r" ] ] ],
            '\\n'    => [ "\n", [ [ Token::WHITESPACE => "\n" ] ] ],
            'space'  => [ " ", [ [ Token::WHITESPACE => " " ] ] ],
            'spaces' => [ " \t\r\n", [ [ Token::WHITESPACE => " \t\r\n" ] ] ],
        ];
    }

    public function testObject()
    {
        $json = '{ "foo": "bar", "num": 1 }';
        $collected = $this->doTestAndCollectTokens($json);

        $expected = [
            [ Token::OBJECT_START => null ],
            [ Token::WHITESPACE => ' ' ],
            [ Token::STRING => 'foo' ],
            [ Token::KEY_DELIMITER => null ],
            [ Token::WHITESPACE => ' ' ],
            [ Token::STRING => 'bar' ],
            [ Token::COMA => null ],
            [ Token::WHITESPACE => ' ' ],
            [ Token::STRING => 'num' ],
            [ Token::KEY_DELIMITER => null ],
            [ Token::WHITESPACE => ' ' ],
            [ Token::NUMBER => 1 ],
            [ Token::WHITESPACE => ' ' ],
            [ Token::OBJECT_END => null ],
        ];
        $this->assertSame($expected, $collected);
    }

    /**
     * @dataProvider malformedNumbers
     * @param $json
     */
    public function testMalformedNumber($json)
    {
        $this->expectException(TokenizerException::class);
        $this->expectExceptionCode(TokenizerException::CODE_MALFORMED_NUMBER);
        $this->doTestAndCollectTokens($json);
    }

    public function malformedNumbers()
    {
        $malformedNumbers = [ '--1', '1.01.01', '1e0.1', '1e1e1', '1.', '1e' ];
        foreach ($malformedNumbers as $malformedNumber) {
            yield $malformedNumber => [ $malformedNumber ];
        }
    }

    /**
     * @dataProvider samples
     */
    public function testSamples($sampleFile)
    {
        $buffer = new SourceBuffer(new FileSource($sampleFile));
        $tokenizer = new Tokenizer($buffer);
        $processed = '';
        foreach ($tokenizer->tokens() as $token) {
            switch ($token->getId()) {
                case Token::WHITESPACE:
                    $processed .= $token->getValue();
                    break;

                case Token::NUMBER:
                case Token::STRING:
                case Token::FALSE:
                case Token::TRUE:
                case Token::NULL:
                    $processed .= json_encode($token->getValue());
                    break;

                default:
                    $processed .= $token->getId();
            }
        }


        $expected = json_decode(file_get_contents($sampleFile), true);
        $this->assertNotNull($expected, json_last_error_msg());

        $this->assertNotEmpty($processed);
        $actual = json_decode($processed, true);
        $this->assertNotNull($actual, json_last_error_msg() . ': ' . PHP_EOL . $processed);

        $this->assertEquals($expected, $actual);
    }

    public function samples()
    {
        yield from $this->getSampleFiles();
    }
}
