<?php
declare(strict_types=1);

namespace JsonDecodeStream\Source;

use JsonDecodeStream\Exception\SourceException;

class StreamSource implements SourceInterface
{
    /** @var resource */
    protected $stream;

    /**
     * StreamSource constructor.
     * @param $stream
     * @throws SourceException
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new SourceException('argument is not a resource');
        }
        $this->stream = $stream;
    }

    public function isEof(): bool
    {
        return feof($this->stream);
    }

    public function read(int $bytes): string
    {
        return fread($this->stream, $bytes);
    }

    public function rewind(): void
    {
        if (ftell($this->stream) === 0) {
            return;
        }

        $streamMetaData = stream_get_meta_data($this->stream);
        if (!$streamMetaData['seekable']) {
            throw new SourceException('This stream is not seekable, can not rewind after data was read');
        }

        fseek($this->stream, 0, SEEK_SET);
    }
}
