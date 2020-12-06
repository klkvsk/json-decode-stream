<?php
declare(strict_types=1);

namespace JsonDecodeStream\Collector;

use JsonDecodeStream\Event;

interface CollectorInterface
{
    public function processEvent(Event $event);
}
