<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use ArrayIterator;
use JsonDecodeStream\Exception\SourceException;
use JsonDecodeStream\Source\FileSource;
use JsonDecodeStream\Source\Psr7Source;
use JsonDecodeStream\Source\SourceInterface;
use JsonDecodeStream\Source\StreamSource;
use JsonDecodeStream\Source\StringIteratorSource;
use JsonDecodeStream\Source\StringSource;
use Nyholm\Psr7\Factory\Psr17Factory;

class SourcesTest extends Test
{
    /**
     * @dataProvider sources
     * @param SourceInterface $source
     */
    public function testShortSource(SourceInterface $source)
    {
        $this->assertFalse($source->isEof());
        $this->assertEquals('foobar', $source->read(100));
        $this->assertTrue($source->isEof());
    }

    /**
     * @dataProvider sources
     * @param SourceInterface $source
     */
    public function testLongSource(SourceInterface $source)
    {
        $this->assertFalse($source->isEof());
        $this->assertEquals('foo', $source->read(3));
        $this->assertFalse($source->isEof());
        $this->assertEquals('bar', $source->read(10));
        $this->assertTrue($source->isEof());
    }

    /**
     * @dataProvider sources
     * @param SourceInterface $source
     */
    public function testRewind(SourceInterface $source)
    {
        $this->assertEquals('foobar', $source->read(10));
        $source->rewind();
        $this->assertEquals('foobar', $source->read(10));
    }

    public function sources()
    {
        $foobarFile = __DIR__ . '/data/source-read-test.txt';
        $psr7DataFactory = new Psr17Factory();
        $psr7Data = $psr7DataFactory->createStreamFromFile($foobarFile);
        $iterableData = function () {
            yield 'fo';
            yield 'ob';
            yield 'ar';
        };

        return [
            StringSource::class         => [ new StringSource('foobar') ],
            FileSource::class           => [ new FileSource($foobarFile) ],
            StreamSource::class         => [ new StreamSource(fopen($foobarFile, 'r')) ],
            Psr7Source::class           => [ new Psr7Source($psr7Data) ],
            StringIteratorSource::class => [ new StringIteratorSource($iterableData) ],
        ];
    }

    public function testPsr7SourceConstructor()
    {
        $foobarFile = __DIR__ . '/data/source-read-test.txt';
        $psr7DataFactory = new Psr17Factory();
        $psr7Data = $psr7DataFactory->createStreamFromFile($foobarFile);
        $psr7Source = new Psr7Source($psr7Data);

        $this->assertSame($psr7Data, $psr7Source->getStream());
    }

    public function testFileSourceNotReadableFileError()
    {
        $this->expectException(SourceException::class);
        $fileSource = new FileSource('/non/readable/file');
        $fileSource->read(1);
    }

    public function testFileSourceFileHandlerManagement()
    {
        $foobarFile = __DIR__ . '/data/source-read-test.txt';
        $handlerCountAtStart = count(get_resources('stream'));
        $fileSource = new FileSource($foobarFile);
        $fileSource->read(1);
        $this->assertCount($handlerCountAtStart + 1, get_resources('stream'));

        unset($fileSource);
        $this->assertCount($handlerCountAtStart, get_resources('stream'));
    }

    public function testStreamSourceNotStreamError()
    {
        $this->expectException(SourceException::class);
        new StreamSource(false);
    }

    public function testNonSeekableStreamSourceRewind()
    {
        $streamSource = new StreamSource(fopen('http://example.com', 'r'));

        // no exception on rewind since no data is read yet
        $streamSource->rewind();
        $streamSource->read(1);

        // can not rewind now after some data is read
        $this->expectException(SourceException::class);
        $streamSource->rewind();
    }

    /**
     * @dataProvider stringIteratorIterables
     * @param $iterable
     * @throws SourceException
     */
    public function testStringIteratorIterables($iterable)
    {
        $stringIteratorSource = new StringIteratorSource($iterable);
        $this->assertFalse($stringIteratorSource->isEof());
        $stringIteratorSource->rewind();
        $this->assertFalse($stringIteratorSource->isEof());
        $this->assertEquals('foobar', $stringIteratorSource->read(6));
        $this->assertSame('', $stringIteratorSource->read(10));
        $this->assertTrue($stringIteratorSource->isEof());
    }

    public function stringIteratorIterables()
    {
        $array = [ 'foo', 'bar' ];
        $generator = function () {
            yield 'foo';
            yield 'bar';
        };
        $iterator = new ArrayIterator($array);

        return [
            'array'     => [ $array ],
            'closure' =>   [ $generator ],
            'generator' => [ $generator() ],
            'iterator'  => [ $iterator ],
        ];
    }

    public function testStringIteratorBadIterable()
    {
        $this->expectException(SourceException::class);
        new StringIteratorSource('foobar');
    }

    public function testStringIteratorNonRewindable()
    {
        $generator = function () {
            yield 'foobar';
        };
        $stringIteratorSource = new StringIteratorSource($generator());
        $stringIteratorSource->rewind();
        $stringIteratorSource->read(1);

        $this->expectException(SourceException::class);
        $stringIteratorSource->rewind();
    }

    public function testStringIteratorBadGenerator()
    {
        $generator = function () {
            yield 'foo';
            yield false;
        };
        $stringIteratorSource = new StringIteratorSource($generator());
        $stringIteratorSource->rewind();
        $stringIteratorSource->read(3);
        $this->expectException(SourceException::class);
        $stringIteratorSource->read(3);
    }
}
