<?php
declare(strict_types=1);


namespace JsonDecodeStream;


use JsonDecodeStream\Internal\Selector;
use JsonDecodeStream\Internal\Stack;

class Event
{
    public const DOCUMENT_START = 'document-start';
    public const DOCUMENT_END = 'document-end';
    public const OBJECT_START = 'object-start';
    public const OBJECT_END = 'object-end';
    public const ARRAY_START = 'array-start';
    public const ARRAY_END = 'array-end';
    public const KEY = 'key';
    public const VALUE = 'value';

    /** @var string */
    protected $id;
    /** @var mixed */
    protected $value;
    /** @var Stack */
    protected $stack;
    /** @var int */
    protected $lineNumber;
    /** @var int */
    protected $charNumber;

    public function __construct(string $id, $value, Stack $stack, int $lineNumber, int $charNumber)
    {
        $this->id = $id;
        $this->value = $value;
        $this->stack = $stack;
        $this->lineNumber = $lineNumber;
        $this->charNumber = $charNumber;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getDepth(): int
    {
        return $this->stack->getDepth();
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        $path = '';
        foreach ($this->stack->frames() as $frame) {
            if ($frame->isArray()) {
                $path .= '[' . $frame->getLastKey() . ']';
            } else if ($frame->isObject()) {
                if (!empty($path)) {
                    $path .= '.';
                }
                $path .= $frame->getLastKey();
            }
        }

        return $path;
    }

    /**
     * @param string $selector
     * @return bool
     * @throws Exception\SelectorException
     */
    public function matchPath(string $selector)
    {
        return Selector::create($selector)->match($this->stack);
    }

    /**
     * @return int
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @return int
     */
    public function getCharNumber(): int
    {
        return $this->charNumber;
    }

}