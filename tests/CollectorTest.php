<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use JsonDecodeStream\Collector\CollectorInterface;
use JsonDecodeStream\Event;
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

    public function testCustomCollectorWithReturn()
    {
        $collector = new class implements CollectorInterface {
            public function processEvent(Event $event)
            {
                if ($event->getId() == Event::VALUE && $event->matchPath('ids[]')) {
                    return [ $event->getPath(), "ID_" . $event->getValue() ];
                } else {
                    return null;
                }
            }
        };

        $items = iterator_to_array($this->parser->items($collector));

        $this->assertArraysAreEqual([ 'ids[0]' => "ID_1", 'ids[1]' => "ID_2", 'ids[2]' => "ID_3" ], $items);
    }


    public function testCustomCollectorWithYield()
    {
        $collector = new class implements CollectorInterface {
            protected $count = 0;
            protected $sum = 0;
            public function processEvent(Event $event)
            {
                if ($event->getId() == Event::VALUE && $event->matchPath('ids[]')) {
                    $this->count++;
                    $this->sum += $event->getValue();
                } else if ($event->getId() == Event::DOCUMENT_END) {
                    yield [ 'count', $this->count ];
                    yield [ 'sum', $this->sum ];
                }
            }
        };

        $items = iterator_to_array($this->parser->items($collector));

        $this->assertArraysAreEqual([ 'count' => 3, 'sum' => 6 ], $items);
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
