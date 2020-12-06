<?php
declare(strict_types=1);

namespace JsonDecodeStream\Exception;

use JsonDecodeStream\Event;

class CollectorException extends JsonDecodeStreamException
{
    const CODE_DUPLICATE_KEY = 1;

    public static function duplicatedKey(string $key, Event $event)
    {
        return new static(
            "Key `$key` is duplicated in {$event->getPath()} at {$event->getLineNumber()}:{$event->getCharNumber()}",
            static::CODE_DUPLICATE_KEY
        );
    }
}
