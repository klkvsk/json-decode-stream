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
            throw new SourceException('Argument is not a resource');
        }
        $this->stream = $stream;
    }

    public function isEof(): bool
    {
        return feof($this->stream);
    }

    public function read(int $bytes): string
    {
        try {
            $read = fread($this->stream, $bytes);
            if ($read === false) {
                $error = error_get_last();
                throw new \RuntimeException($error ? $error['message'] : 'fread error');
            }
        } catch (\Throwable $e) {
            throw new SourceException('Cound not read from stream', 0, $e);
        }

        return $read;
    }

    public function rewind(): void
    {
        try {
            $tell = ftell($this->stream);
            if ($tell === false) {
                $error = error_get_last();
                throw new \RuntimeException($error ? $error['message'] : 'ftell error');
            }
            if ($tell === 0) {
                return;
            }

            $streamMetaData = stream_get_meta_data($this->stream);
            if (!$streamMetaData['seekable']) {
                throw new SourceException('This stream is not seekable, can not rewind after data was read');
            }

            $seek = fseek($this->stream, 0, SEEK_SET);
            if ($seek === -1) {
                $error = error_get_last();
                throw new \RuntimeException($error ? $error['message'] : 'fseek error');
            }
        } catch (\Throwable $e) {
            throw new SourceException('Cound not seek the stream', 0, $e);
        }
    }
}
