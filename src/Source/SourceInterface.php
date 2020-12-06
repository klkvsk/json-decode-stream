<?php
declare(strict_types=1);

namespace JsonDecodeStream\Source;

interface SourceInterface
{
    public function isEof(): bool;

    public function read(int $bytes): string;

    public function rewind(): void;
}
