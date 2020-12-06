<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use JsonDecodeStream\Parser;

class CollectorTest extends Test
{
    protected $parser;
    
    protected function setUp(): void
    {
        $json =
            /** @lang JSON */
            '{
              "total": 2,
              "ids": [ 1, 2, 3],
              "users": [
                { "name":  "Alice", "age": 20, "skills":  [ "php", "javascript" ]},
                { "name":  "Bob", "age": 30, "skills":  [ "c++", "python" ]}
              ],
              "object": {
                "foo": {
                  "bar": true
                }
              },
              "different_types": [
                { "a": 1 },
                { "a": true },
                { "a": [ 1 ] },
                { "a": { "b": 1 }}
              ]
            }';

        $this->parser = Parser::fromString($json);
    }

    /**
     * @dataProvider itemsCollectorDataProvider
     * @param $selector
     * @param $expected
     * @throws \JsonDecodeStream\Exception\CollectorException
     * @throws \JsonDecodeStream\Exception\ParserException
     * @throws \JsonDecodeStream\Exception\SelectorException
     * @throws \JsonDecodeStream\Exception\TokenizerException
     */
    public function testItemsCollector($selector, $expected)
    {
        $items = [];
        foreach ($this->parser->items($selector) as $key => $item) {
            $items []= [ $key => $item ];
        }

        $this->assertArraysAreEqual($expected, $items);
    }

    public function itemsCollectorDataProvider()
    {
        yield 'simple' => [ 'total', [ [ 'total' => 2 ] ] ];
        yield 'array' => [ 'ids', [ [ 'ids' => [ 1, 2, 3 ] ] ] ];
        yield 'array each' => [ 'ids[]', [
            [ 'ids[0]' => 1 ],
            [ 'ids[1]' => 2 ],
            [ 'ids[2]' => 3 ],
        ] ];
        yield 'sub object' => [ 'object.foo', [
            [ 'object.foo' => (object)[ 'bar' => true ] ],
        ] ];
        yield 'different types' => [ 'different_types[1:].a', [
            [ 'different_types[1].a' => true ],
            [ 'different_types[2].a' => [ 1 ] ],
            [ 'different_types[3].a' => (object)[ 'b' => 1 ] ],
        ] ];
    }

    protected function assertArraysAreEqual($expected, $actual)
    {
        $this->assertEquals(
            json_encode($expected),
            json_encode($actual)
        );
    }
}
