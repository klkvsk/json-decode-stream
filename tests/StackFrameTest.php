<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;

use JsonDecodeStream\Internal\StackFrame;

class StackFrameTest extends Test
{
    public function testIsArray()
    {
        $frame = StackFrame::array();
        $this->assertTrue($frame->isArray());
        $this->assertFalse($frame->isObject());
    }

    public function testIsObject()
    {
        $frame = StackFrame::object();
        $this->assertTrue($frame->isObject());
        $this->assertFalse($frame->isArray());
    }

    public function testObjectAwaitsKeyInitially()
    {
        $objectFrame = StackFrame::object();
        $this->assertTrue($objectFrame->isAwaitsKey());

        $arrayFrame = StackFrame::array();
        $this->assertFalse($arrayFrame->isAwaitsKey());
    }

    public function testProperties()
    {
        $frame = StackFrame::object();
        $this->assertFalse($frame->isAwaitsKeyDelimiter());
        $frame->setAwaitsKeyDelimiter(true);
        $this->assertTrue($frame->isAwaitsKeyDelimiter());

        $this->assertFalse($frame->isAwaitsComa());
        $frame->setAwaitsComa(true);
        $this->assertTrue($frame->isAwaitsComa());

        $this->assertEquals(0, $frame->getElementCount());
        $frame->incrementElementCount();
        $this->assertEquals(1, $frame->isAwaitsComa());

        $this->assertNull($frame->getLastKey());
        $frame->setLastKey('foo');
        $this->assertEquals('foo', $frame->getLastKey());
    }
}
