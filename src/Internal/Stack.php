<?php
declare(strict_types=1);

namespace JsonDecodeStream\Internal;

use SplStack;

class Stack
{
    protected $stack;

    public function __construct()
    {
        $this->stack = new SplStack();
    }

    public function push(StackFrame $frame)
    {
        $this->stack->push($frame);
    }

    public function pop()
    {
        $this->stack->pop();
    }

    /** @return StackFrame */
    public function current()
    {
        return $this->stack->top();
    }

    /** @return StackFrame */
    public function root()
    {
        return $this->stack->bottom();
    }

    public function isEmpty()
    {
        return $this->stack->isEmpty();
    }

    public function getDepth()
    {
        return $this->stack->count();
    }

    /**
     * @return StackFrame[]
     */
    public function frames()
    {
        return array_reverse(iterator_to_array($this->stack));
    }

    public function __clone()
    {
        $this->stack = clone $this->stack;
    }

}