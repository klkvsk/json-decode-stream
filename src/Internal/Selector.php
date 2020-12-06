<?php
declare(strict_types=1);


namespace JsonDecodeStream\Internal;

use JsonDecodeStream\Exception\SelectorException;
use UnexpectedValueException;

class Selector
{
    /** @var static[] */
    protected static $cache = [];
    protected $selector;
    protected $selectorStack;

    protected function __construct(string $selector, array $selectorStack)
    {
        $this->selector = $selector;
        $this->selectorStack = $selectorStack;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getSelectorStack(): array
    {
        return $this->selectorStack;
    }

    public static function create(string $selector)
    {
        if (empty($selector)) {
            throw new SelectorException('selector should not be empty');
        }

        if (isset(static::$cache[$selector])) {
            return static::$cache[$selector];
        }

        $selectorStack = [];

        $offset = 0;
        while ($offset < strlen($selector)) {
            $part = substr($selector, $offset);
            $matches = null;
            if (preg_match('/^\[]/', $part, $matches)) {
                // `[]`
                array_push(
                    $selectorStack,
                    [ 'type' => 'any' ]
                );
            } elseif ($offset == 0 && preg_match('/^([a-z_\$][a-z0-9_\$]*)/i', $part, $matches)) {
                // `foo` but only as a beginning of selector
                array_push(
                    $selectorStack,
                    [ 'type' => 'key', 'key' => $matches[1] ]
                );
            } elseif (preg_match('/^\.([a-z_\$][a-z0-9_\$]*)/i', $part, $matches)) {
                // `.foo`
                array_push(
                    $selectorStack,
                    [ 'type' => 'key', 'key' => $matches[1] ]
                );
            } elseif (preg_match('/^\[(0|[1-9][0-9]*)]/', $part, $matches)) {
                // `[1]`
                array_push(
                    $selectorStack,
                    [ 'type' => 'index', 'index' => (int)$matches[1] ]
                );
            } elseif (preg_match('/^\[(0|[1-9][0-9]*)?:(0|[1-9][0-9]*)?]/', $part, $matches)) {
                // `[1:9]` or `[1:]` or `[:9]`
                $firstIndex = strlen($matches[1] ?? '') ? (int)$matches[1] : null;
                $lastIndex = strlen($matches[2] ?? '') ? (int)$matches[2] : null;
                if ($firstIndex === null && $lastIndex === null) {
                    throw new SelectorException('Wrong array range selector: "[:]"');
                }
                array_push(
                    $selectorStack,
                    [ 'type' => 'range', 'start' => $firstIndex, 'end' => $lastIndex ]
                );
            } elseif (preg_match('/^\["((?:[^"\\\\]+|\\\\.)*)"]/', $part, $matches)) {
                // `["foo"]` and also `["The \"Foo\" key"]`
                array_push(
                    $selectorStack,
                    [ 'type' => 'key', 'key' => $matches[1] ]
                );
            }

            if ($matches) {
                $offset += strlen($matches[0]);
            } else {
                throw new SelectorException('Wrong selector at `' . $part . '`');
            }
        }

        return static::$cache[$selector] = new static($selector, $selectorStack);
    }

    public function match(Stack $stack)
    {
        $stackFrames = $stack->frames();
        foreach ($this->getSelectorStack() as $i => $selStackFrame) {
            $stackFrame = $stackFrames[$i] ?? null;

            if ($stackFrame == null) {
                return false;
            }

            switch ($selStackFrame['type']) {
                case 'any':
                    break;

                case 'key':
                    if (!$stackFrame->isObject()) {
                        return false;
                    }
                    if ($stackFrame->getLastKey() !== $selStackFrame['key']) {
                        return false;
                    }
                    break;

                case 'index':
                    if (!$stackFrame->isArray()) {
                        return false;
                    }
                    if ($stackFrame->getLastKey() !== $selStackFrame['index']) {
                        return false;
                    }
                    break;

                case 'range':
                    if (!$stackFrame->isArray()) {
                        return false;
                    }
                    if ($selStackFrame['start'] !== null && $stackFrame->getLastKey() < $selStackFrame['start']) {
                        return false;
                    }
                    if ($selStackFrame['end'] !== null && $stackFrame->getLastKey() > $selStackFrame['end']) {
                        return false;
                    }
                    break;

                default:
                    throw new UnexpectedValueException($selStackFrame['type']);
            }
        }

        return true;
    }
}
