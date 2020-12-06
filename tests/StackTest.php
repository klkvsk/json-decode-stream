<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;



use JsonDecodeStream\Internal\Stack;
use JsonDecodeStream\Internal\StackFrame;

class StackTest extends Test
{
    public function testStackPushPop()
    {
        $stack = new Stack();

        $a = StackFrame::object();
        $a->setLastKey('a');
        $stack->push($a);

        $b = StackFrame::object();
        $b->setLastKey('b');
        $stack->push($b);

        $c = StackFrame::object();
        $c->setLastKey('c');
        $stack->push($c);

        $this->assertSame($a, $stack->root());
        $this->assertSame($c, $stack->current());

        $stack->pop();
        $this->assertSame($b, $stack->current());
    }

    public function testStackIsEmpty()
    {
        $stack = new Stack();

        $this->assertTrue($stack->isEmpty());

        $a = StackFrame::object();
        $a->setLastKey('a');
        $stack->push($a);

        $this->assertFalse($stack->isEmpty());

        $stack->pop();

        $this->assertTrue($stack->isEmpty());
    }

    public function testStackCloning()
    {
        $stack = new Stack();

        $a = StackFrame::object();
        $a->setLastKey('a');
        $stack->push($a);

        $copy = clone $stack;

        $b = StackFrame::object();
        $b->setLastKey('b');
        $copy->push($b);

        $this->assertSame($a, $stack->current());
        $this->assertSame($b, $copy->current());
    }

    public function testStackDepth()
    {
        $stack = new Stack();

        $this->assertEquals(0, $stack->getDepth());

        $a = StackFrame::object();
        $a->setLastKey('a');
        $stack->push($a);

        $this->assertEquals(1, $stack->getDepth());

        $b = StackFrame::object();
        $b->setLastKey('b');
        $stack->push($b);

        $this->assertEquals(2, $stack->getDepth());

        $stack->pop();

        $this->assertEquals(1, $stack->getDepth());
    }

    public function testStackFrames()
    {
        $stack = new Stack();

        $a = StackFrame::object();
        $a->setLastKey('a');
        $stack->push($a);

        $b = StackFrame::object();
        $b->setLastKey('b');
        $stack->push($b);

        $stackFrames = $stack->frames();

        $this->assertEquals([ $a, $b ], $stackFrames);
    }

}
