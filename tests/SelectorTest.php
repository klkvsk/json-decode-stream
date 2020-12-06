<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;


use JsonDecodeStream\Event;
use JsonDecodeStream\Exception\SelectorException;
use JsonDecodeStream\Internal\Selector;
use JsonDecodeStream\Parser;

class SelectorTest extends Test
{
    public function testSelectorStored()
    {
        $selector = Selector::create('foo');
        $this->assertEquals('foo', $selector->getSelector());
    }

    public function testRootKey()
    {
        $selector = Selector::create('foo');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootKeyWithDot()
    {
        $selector = Selector::create('.foo');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootAny()
    {
        $selector = Selector::create('[]');
        $this->assertSame(
            [
                [ "type" => "any" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootIndex()
    {
        $selector = Selector::create('[1]');
        $this->assertSame(
            [
                [ "type" => "index", "index" => 1 ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootRangeStartEnd()
    {
        $selector = Selector::create('[1:2]');
        $this->assertSame(
            [
                [ "type" => "range", "start" => 1, "end" => 2 ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootRangeStart()
    {
        $selector = Selector::create('[1:]');
        $this->assertSame(
            [
                [ "type" => "range", "start" => 1, "end" => null ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootRangeEnd()
    {
        $selector = Selector::create('[:2]');
        $this->assertSame(
            [
                [ "type" => "range", "start" => null, "end" => 2 ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootKeyAsIndex()
    {
        $selector = Selector::create('["foo"]');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRootKeyAsIndexWithEscapedQuotes()
    {
        $selector = Selector::create('["The \"Foo\" key"]');
        $this->assertSame(
            [
                [ "type" => "key", "key" => 'The \"Foo\" key' ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testKeyInKey()
    {
        $selector = Selector::create('foo.bar');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
                [ "type" => "key", "key" => "bar" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testKeyInKeyWithDot()
    {
        $selector = Selector::create('.foo.bar');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
                [ "type" => "key", "key" => "bar" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testKeyInKeyInKeySingleLetters()
    {
        $selector = Selector::create('a.b.c');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "a" ],
                [ "type" => "key", "key" => "b" ],
                [ "type" => "key", "key" => "c" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testIndexKeyInKey()
    {
        $selector = Selector::create('foo["bar"]');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
                [ "type" => "key", "key" => "bar" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testKeyInIndexKey()
    {
        $selector = Selector::create('["foo"].bar');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
                [ "type" => "key", "key" => "bar" ],
            ],
            $selector->getSelectorStack()
        );
    }

    public function testRangeInKey()
    {
        $selector = Selector::create('foo[1:2]');
        $this->assertSame(
            [
                [ "type" => "key", "key" => "foo" ],
                [ "type" => "range", "start" => 1, "end" => 2 ],
            ],
            $selector->getSelectorStack()
        );
    }
    public function testKeyInRange()
    {
        $selector = Selector::create('[1:2].foo');
        $this->assertSame(
            [
                [ "type" => "range", "start" => 1, "end" => 2 ],
                [ "type" => "key", "key" => "foo" ],
            ],
            $selector->getSelectorStack()
        );
    }


    /**
     * @dataProvider badSelectors
     * @param string $badSelector
     * @throws SelectorException
     */
    public function testBadSelectors(string $badSelector)
    {
        $this->expectException(SelectorException::class);
        Selector::create($badSelector);
    }

    public function badSelectors()
    {
        return [
            [ '' ],
            [ '.' ],
            [ '..' ],
            [ 'foo!' ],
            [ '[-1]' ],
            [ '[:]' ],
            [ '["a"b"c"]' ],
            [ 'foo.' ],
        ];
    }

    /**
     * @dataProvider samplesSelectors
     * @param $sampleFile
     * @throws \JsonDecodeStream\Exception\ParserException
     * @throws \JsonDecodeStream\Exception\TokenizerException
     */
    public function testSamplesSelectors($sampleFile)
    {
        $parser = Parser::fromFile($sampleFile);
        foreach ($parser->events() as $event) {
            if ($event->getId() == Event::VALUE) {
                $this->assertEquals($event->getValue(), $event->getPath());
            }
        }
    }

    /**
     * @dataProvider samplesDepth
     * @param $sampleFile
     * @throws \JsonDecodeStream\Exception\ParserException
     * @throws \JsonDecodeStream\Exception\TokenizerException
     */
    public function testSamplesDepth($sampleFile)
    {
        $parser = Parser::fromFile($sampleFile);
        foreach ($parser->events() as $event) {
            if ($event->getId() == Event::VALUE) {
                $this->assertEquals($event->getValue(), $event->getDepth());
            }
        }
    }

    public function samplesSelectors()
    {
        yield from $this->getSampleFiles('selectors');
    }

    public function samplesDepth()
    {
        yield from $this->getSampleFiles('depth');
    }

}
