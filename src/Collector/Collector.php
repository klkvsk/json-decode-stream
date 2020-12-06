<?php
declare(strict_types=1);

namespace JsonDecodeStream\Collector;

use JsonDecodeStream\Event;
use JsonDecodeStream\Exception\CollectorException;
use JsonDecodeStream\Exception\SelectorException;
use stdClass;

class Collector implements CollectorInterface
{
    /** @var string */
    protected $selector;
    /** @var bool */
    protected $objectsAsAssocArrays;

    /** @var null|array|stdClass */
    protected $current = null;
    /** @var array|array[]|stdClass[] */
    protected $stack = [];
    /** @var null|string */
    protected $key = null;
    /** @var string[]|null[] */
    protected $keyStack = [];

    public function __construct(string $selector, bool $objectsAsAssocArrays = false)
    {
        $this->selector = $selector;
        $this->objectsAsAssocArrays = $objectsAsAssocArrays;
    }

    /**
     * @param Event $event
     * @return array|null
     * @throws CollectorException
     * @throws SelectorException
     */
    public function processEvent(Event $event)
    {
        if (!$event->matchPath($this->selector)) {
            return null;
        }

        switch ($event->getId()) {
            case Event::KEY:
                $this->key = $event->getValue();
                break;

            case Event::VALUE:
                if ($this->current === null) {
                    return [ $event->getPath(), $event->getValue() ];
                } elseif ($this->key !== null && $this->hasKeyInCurrent($this->key)) {
                    throw CollectorException::duplicatedKey($this->key, $event);
                } else {
                    $this->setInCurrent($event->getValue(), $this->key);
                }
                break;

            case Event::OBJECT_START:
            case Event::ARRAY_START:
                if ($this->current !== null) {
                    $this->stack[] = $this->current;
                    $this->keyStack[] = $this->key;
                }
                if ($event->getId() == Event::OBJECT_START) {
                    $this->current = $this->objectsAsAssocArrays ? [] : new stdClass();
                } elseif ($event->getId() == Event::ARRAY_START) {
                    $this->current = [];
                }
                $this->key = null;
                break;

            case Event::OBJECT_END:
            case Event::ARRAY_END:
                $finished = $this->current;
                if (!empty($this->stack)) {
                    $this->current = array_pop($this->stack);
                    $this->key = array_pop($this->keyStack);
                    if ($this->key !== null && $this->hasKeyInCurrent($this->key)) {
                        throw CollectorException::duplicatedKey($this->key, $event);
                    }
                    $this->setInCurrent($finished, $this->key);
                } else {
                    $this->current = null;
                    $this->key = null;
                    return [ $event->getPath(), $finished ];
                }
                break;
        }

        return null;
    }

    protected function hasKeyInCurrent($key)
    {
        if ($key === null) {
            return false;
        }
        if (is_object($this->current)) {
            return isset($this->current->{$key});
        } elseif (is_array($this->current)) {
            return isset($this->current[$key]);
        } else {
            return false;
        }
    }

    protected function setInCurrent($value, $key = null)
    {
        if (is_object($this->current)) {
            $this->current->{$key} = $value;
        } elseif (is_array($this->current) && $key === null) {
            $this->current[] = $value;
        } elseif (is_array($this->current)) {
            $this->current[$key] = $value;
        }
    }
}
