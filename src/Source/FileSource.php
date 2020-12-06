<?php
declare(strict_types=1);

namespace JsonDecodeStream\Source;

use JsonDecodeStream\Exception\SourceException;
use Throwable;

class FileSource implements SourceInterface
{
    /** @var string */
    protected $filename;
    /** @var resource|null */
    protected $handle;
    /** @var StreamSource|null */
    protected $streamSource;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    /**
     * @return resource
     * @throws SourceException
     */
    protected function file()
    {
        if (!$this->handle) {
            $exception = null;
            try {
                $this->handle = fopen($this->filename, 'r');
            } catch (Throwable $e) {
                $this->handle = null;
                $exception = $e;
            }
            if (!$this->handle) {
                throw new SourceException("could not open file '{$this->filename}'", 0, $exception);
            }
        }

        return $this->handle;
    }

    protected function stream()
    {
        if (!$this->streamSource) {
            $this->streamSource = new StreamSource($this->file());
        }

        return $this->streamSource;
    }

    public function isEof(): bool
    {
        return $this->stream()->isEof();
    }

    public function read(int $bytes): string
    {
        return $this->stream()->read($bytes);
    }

    public function rewind(): void
    {
        $this->stream()->rewind();
    }

}