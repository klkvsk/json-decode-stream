<?php
declare(strict_types=1);

namespace JsonDecodeStream\Source;


class StringSource implements SourceInterface
{
    /** @var string */
    protected $string;
    /** @var int */
    protected $position = 0;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function isEof(): bool
    {
        return $this->position == strlen($this->string);
    }

    public function read(int $bytes): string
    {
        $part = substr($this->string, $this->position, $bytes);
        $this->position += strlen($part);
        return $part;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }


}