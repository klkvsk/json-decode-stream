<?php
declare(strict_types=1);

namespace JsonDecodeStream\Source;

use ArrayIterator;
use IteratorIterator;
use JsonDecodeStream\Exception\SourceException;
use Throwable;
use Traversable;

class StringIteratorSource implements SourceInterface
{
    /** @var IteratorIterator */
    protected $stringIterator;
    /** @var string|null */
    protected $previousPart;
    /** @var callable|null */
    protected $stringIteratorFactory;
    /** @var bool */
    protected $isRewoundInitially = false;

    /**
     * StringIteratorSource constructor.
     * @param $iterable
     * @throws SourceException
     */
    public function __construct($iterable)
    {
        if (is_callable($iterable)) {
            $this->stringIteratorFactory = $iterable;
            $iterable = call_user_func($iterable);
        }
        $this->import($iterable);
    }

    /**
     * @param $iterable array|iterable|Traversable
     * @throws SourceException
     */
    protected function import($iterable)
    {
        if (is_array($iterable)) {
            $iterable = new ArrayIterator($iterable);
        }

        if ($iterable instanceof Traversable) {
            $this->stringIterator = new IteratorIterator($iterable);
        } else {
            throw new SourceException(
                'Can not iterate over '
                . (is_object($iterable) ? get_class($iterable) : gettype($iterable))
            );
        }
        $this->isRewoundInitially = false;
    }

    public function isEof(): bool
    {
        return $this->isRewoundInitially && empty($this->previousPart) && !$this->stringIterator->valid();
    }

    /**
     * @param int $bytes
     * @return string
     * @throws SourceException
     */
    public function read(int $bytes): string
    {
        if (!$this->isRewoundInitially && !$this->stringIterator->valid()) {
            $this->stringIterator->rewind();
            $this->isRewoundInitially = true;
        }

        $part = $this->previousPart ?? '';
        $this->previousPart = null;

        while (strlen($part) < $bytes && $this->stringIterator->valid()) {
            $nextPart = $this->stringIterator->current();
            if (!is_string($nextPart)) {
                throw new SourceException('Iterator provided a non-string value');
            }
            $part .= $nextPart;
            $this->stringIterator->next();
        }

        if (strlen($part) > $bytes) {
            $this->previousPart = substr($part, $bytes);
            $part = substr($part, 0, $bytes);
        }

        return $part;
    }

    /**
     * @throws SourceException
     */
    public function rewind(): void
    {
        if (!$this->isRewoundInitially) {
            return;
        }

        try {
            if ($this->stringIteratorFactory) {
                $recreatedIterator = call_user_func($this->stringIteratorFactory);
                $this->import($recreatedIterator);
            } else {
                $this->stringIterator->rewind();
            }
        } catch (Throwable $exception) {
            throw new SourceException('This iterator is not rewindable', 0, $exception);
        }
    }
}
