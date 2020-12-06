<?php
declare(strict_types=1);

namespace JsonDecodeStream\Internal;

class StackFrame
{
    const TYPE_ARRAY = 1;
    const TYPE_OBJECT = 2;

    protected $type;
    protected $elementCount = 0;
    protected $lastKey = null;
    protected $awaitsComa = false;
    protected $awaitsKey = false;
    protected $awaitsKeyDelimiter = false;

    protected function __construct($type)
    {
        $this->type = $type;
    }

    public static function array()
    {
        return new static(self::TYPE_ARRAY);
    }

    public static function object()
    {
        $frame = new static(self::TYPE_OBJECT);
        $frame->setAwaitsKey(true);
        return $frame;
    }

    public function isArray()
    {
        return $this->type == self::TYPE_ARRAY;
    }

    public function isObject()
    {
        return $this->type == self::TYPE_OBJECT;
    }

    public function setAwaitsComa(bool $is)
    {
        $this->awaitsComa = $is;
    }

    public function isAwaitsComa()
    {
        return $this->awaitsComa;
    }

    public function setAwaitsKey(bool $is)
    {
        $this->awaitsKey = $is;
    }

    public function isAwaitsKey()
    {
        return $this->awaitsKey;
    }

    public function setAwaitsKeyDelimiter(bool $is)
    {
        $this->awaitsKeyDelimiter = $is;
    }

    public function isAwaitsKeyDelimiter()
    {
        return $this->awaitsKeyDelimiter;
    }

    public function setLastKey($key)
    {
        $this->lastKey = $key;
    }

    public function getLastKey()
    {
        return $this->lastKey;
    }

    public function incrementElementCount()
    {
        return $this->elementCount++;
    }

    public function getElementCount()
    {
        return $this->elementCount;
    }


}