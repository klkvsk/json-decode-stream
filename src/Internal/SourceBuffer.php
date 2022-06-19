<?php
declare(strict_types=1);


namespace JsonDecodeStream\Internal;

use Iterator;
use JsonDecodeStream\Source\SourceInterface;

class SourceBuffer implements Iterator
{
    protected $source;
    protected $bufferMaxSize;
    protected $bufferSize;
    protected $buffer;
    protected $bufferPosition;
    protected $sourcePosition;

    public function __construct(SourceInterface $source, int $bufferSize = 4096)
    {
        $this->source = $source;
        $this->bufferMaxSize = $bufferSize;
        $this->sourcePosition = 0;
    }

    protected function nextBuffer()
    {
        if ($this->source->isEof()) {
            $this->buffer = null;
        } else {
            $this->buffer = $this->source->read($this->bufferMaxSize);
            $this->bufferPosition = 0;
            $this->bufferSize = strlen($this->buffer);
        }
    }

    public function current(): ?string
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->buffer[$this->bufferPosition];
    }

    public function next(): void
    {
        $this->bufferPosition++;
        if ($this->bufferPosition == min($this->bufferMaxSize, $this->bufferSize)) {
            $this->nextBuffer();
        }
        if ($this->buffer !== null) {
            $this->sourcePosition++;
        }
    }

    public function key(): int
    {
        return $this->sourcePosition;
    }

    public function valid(): bool
    {
        return $this->buffer !== null
            && $this->bufferPosition < $this->bufferSize;
    }

    public function rewind(): void
    {
        $this->source->rewind();
        $this->nextBuffer();
    }
}
