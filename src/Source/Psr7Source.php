<?php
declare(strict_types=1);

namespace JsonDecodeStream\Source;

use Psr\Http\Message\StreamInterface;

class Psr7Source implements SourceInterface
{
    /** @var StreamInterface */
    protected $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function isEof(): bool
    {
        return $this->stream->eof();
    }

    public function read(int $bytes): string
    {
        return $this->stream->read($bytes);
    }

    public function rewind(): void
    {
        if ($this->stream->isSeekable()) {
            $this->stream->rewind();
        }
    }

    public function getStream()
    {
        return $this->stream;
    }
}
