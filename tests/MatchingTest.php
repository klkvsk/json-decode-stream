<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use JsonDecodeStream\Event;
use JsonDecodeStream\Parser;

class MatchingTest extends Test
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
     * @dataProvider valuesCollectorProvider
     * @param $selector
     * @param $expected
     */
    public function testValuesCollector($selector, $expected)
    {
        $collected = $this->collectValues($selector);
        $this->assertArraysAreEqual($expected, $collected);
    }

    public function valuesCollectorProvider()
    {
        yield 'total' => [ 'total', [ 2 ] ];
        yield 'ids' => [ 'ids[]', [ 1, 2, 3 ] ];
        yield 'all ages' => [ 'users[].age', [ 20, 30 ] ];
    }

    /**
     * @dataProvider eventsCollectorProvider
     * @param $selector
     * @param $expected
     */
    public function testEvents($selector, $expected)
    {
        $collected = $this->collectEvents($selector);
        $this->assertArraysAreEqual($expected, $collected);
    }

    protected function assertArraysAreEqual($expected, $actual)
    {
        $this->assertEquals(
            json_encode($expected),
            json_encode($actual)
        );
    }

    public function eventsCollectorProvider()
    {
        yield 'total' => [ 'total', [
            [ Event::VALUE => 2 ]
        ] ];

        yield 'ids[]' => [ 'ids[]', [
            [ Event::VALUE => 1 ],
            [ Event::VALUE => 2 ],
            [ Event::VALUE => 3 ],
        ] ];

        yield 'ids' => [ 'ids', [
            [ Event::ARRAY_START => null ],
            [ Event::VALUE => 1 ],
            [ Event::VALUE => 2 ],
            [ Event::VALUE => 3 ],
            [ Event::ARRAY_END => null ],
        ] ];

        yield 'bob' => [ 'users[1]', [
            [ Event::OBJECT_START => null ],
            [ Event::KEY => "name" ],
            [ Event::VALUE => "Bob" ],
            [ Event::KEY => "age" ],
            [ Event::VALUE => 30 ],
            [ Event::KEY => "skills" ],
            [ Event::ARRAY_START => null ],
            [ Event::VALUE => "c++" ],
            [ Event::VALUE => "python" ],
            [ Event::ARRAY_END => null ],
            [ Event::OBJECT_END => null ],
        ] ];

        yield 'different_types[]' => [ 'different_types[]', [
            [ Event::OBJECT_START => null ],
            [ Event::KEY => 'a' ],
            [ Event::VALUE => 1 ],
            [ Event::OBJECT_END => null ],

            [ Event::OBJECT_START => null ],
            [ Event::KEY => 'a' ],
            [ Event::VALUE => true ],
            [ Event::OBJECT_END => null ],

            [ Event::OBJECT_START => null ],
            [ Event::KEY => 'a' ],
            [ Event::ARRAY_START => null ],
            [ Event::VALUE => 1 ],
            [ Event::ARRAY_END => null ],
            [ Event::OBJECT_END => null ],

            [ Event::OBJECT_START => null ],
            [ Event::KEY => 'a' ],
            [ Event::OBJECT_START => null ],
            [ Event::KEY => 'b' ],
            [ Event::VALUE => 1 ],
            [ Event::OBJECT_END => null ],
            [ Event::OBJECT_END => null ],
        ] ];

        yield 'different_types[].a' => [ 'different_types[].a', [
            [ Event::VALUE => 1 ],

            [ Event::VALUE => true ],

            [ Event::ARRAY_START => null ],
            [ Event::VALUE => 1 ],
            [ Event::ARRAY_END => null ],

            [ Event::OBJECT_START => null ],
            [ Event::KEY => 'b' ],
            [ Event::VALUE => 1 ],
            [ Event::OBJECT_END => null ],
        ] ];

        yield 'different_types[1:2].a' => [ 'different_types[1:2].a', [
            [ Event::VALUE => true ],

            [ Event::ARRAY_START => null ],
            [ Event::VALUE => 1 ],
            [ Event::ARRAY_END => null ],
        ] ];
    }

    protected function collectEvents($selector)
    {
        $collected = [];
        foreach ($this->parser->events() as $event) {
            if ($event->matchPath($selector)) {
                $collected []= [ $event->getId() => $event->getValue() ];
            }
        }
        
        return $collected;
    }
    
    protected function collectValues($selector)
    {
        $collected = [];
        foreach ($this->parser->events() as $event) {
            if ($event->getId() == Event::VALUE && $event->matchPath($selector)) {
                $collected[] = $event->getValue();
            }
        }
        
        return $collected;
    }
}
