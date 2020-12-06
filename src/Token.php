<?php
declare(strict_types=1);


namespace JsonDecodeStream;

class Token
{
    public const OBJECT_START = '{';
    public const OBJECT_END = '}';
    public const ARRAY_START = '[';
    public const ARRAY_END = ']';
    public const KEY_DELIMITER = ':';
    public const COMA = ',';
    public const TRUE = 'true';
    public const FALSE = 'false';
    public const NULL = 'null';
    public const STRING = 'string';
    public const NUMBER = 'number';
    public const WHITESPACE = 'space';

    /** @var string */
    protected $id;
    /** @var mixed */
    protected $value;
    /** @var int */
    protected $lineNumber;
    /** @var int */
    protected $charNumber;

    public function __construct(string $id, $value, int $lineNumber, int $charNumber)
    {
        $this->id = $id;
        $this->value = $value;
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
