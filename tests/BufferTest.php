<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use JsonDecodeStream\Internal\SourceBuffer;
use JsonDecodeStream\Source\Psr7Source;
use JsonDecodeStream\Source\StringSource;
use Nyholm\Psr7\Factory\Psr17Factory;

class BufferTest extends Test
{
    /**
     * @dataProvider bufferSizes
     * @param int $size
     */
    public function testSourceBufferSizes(int $size)
    {
        $buffer = new SourceBuffer(new StringSource('foobar'), $size);
        $this->assertInstanceOf(\Iterator::class, $buffer);
        $this->assertSame([ 'f', 'o', 'o', 'b', 'a', 'r' ], iterator_to_array($buffer));
    }

    public function bufferSizes()
    {
        return [ [100], [6], [2], [1] ];
    }

    public function testValidBeforeRewind()
    {
        $buffer = new SourceBuffer(new StringSource('foobar'));
        $this->assertFalse($buffer->valid());
    }

    public function testValidAfterRewind()
    {
        $buffer = new SourceBuffer(new StringSource('foobar'));
        $buffer->rewind();
        $this->assertTrue($buffer->valid());
    }

    public function testCurrentBeforeRewind()
    {
        $buffer = new SourceBuffer(new StringSource('foobar'));
        $this->assertNull($buffer->current());
    }

    public function testCurrentAfterRewind()
    {
        $buffer = new SourceBuffer(new StringSource('foobar'));
        $buffer->rewind();
        $this->assertEquals('f', $buffer->current());
    }

    public function testZeroBuffer()
    {
        $buffer = new SourceBuffer(new StringSource('0'));
        $buffer->rewind();
        $this->assertTrue($buffer->valid());
        $this->assertEquals('0', $buffer->current());
        $buffer->next();
        $this->assertFalse($buffer->valid());
    }

    public function testEmptyStringBuffer()
    {
        $buffer = new SourceBuffer(new StringSource(''));
        $buffer->rewind();
        $this->assertFalse($buffer->valid());
    }

    public function testEmptyStreamBuffer()
    {
        $psr7DataFactory = new Psr17Factory();
        $psr7Data = $psr7DataFactory->createStream('');
        $buffer = new SourceBuffer(new Psr7Source($psr7Data));
        $buffer->rewind();
        $this->assertFalse($buffer->valid());
    }
}
