# json-decode-stream
![Min PHP version](https://img.shields.io/packagist/php-v/klkvsk/json-decode-stream)
[![Build Status](https://github.com/klkvsk/json-decode-stream/workflows/Tests/badge.svg)](https://github.com/klkvsk/json-decode-stream/actions)
[![Scrutinizer tests](https://img.shields.io/scrutinizer/build/g/klkvsk/json-decode-stream?label=scrutinizer)](https://scrutinizer-ci.com/g/klkvsk/json-decode-stream/inspections)
[![Scrutinizer coverage](https://img.shields.io/scrutinizer/coverage/g/klkvsk/json-decode-stream)](https://scrutinizer-ci.com/g/klkvsk/json-decode-stream/code-structure/master/code-coverage/src/)
[![Scrutinizer code quality](https://img.shields.io/scrutinizer/quality/g/klkvsk/json-decode-stream)](https://scrutinizer-ci.com/g/klkvsk/json-decode-stream/reports/)
[![Packagist version](https://img.shields.io/packagist/v/klkvsk/json-decode-stream)](https://packagist.org/packages/klkvsk/json-decode-stream)

This is a JSON parsing library that allows parsing stream of JSON data. 
You can process JSON records on the fly without decoding complete structure into memory first.
This is especially useful when parsing large JSON files.

## Installation
```shell script
composer require klkvsk/json-decode-stream
``` 

## Basic usage
In most cases, streaming parser is used to parse lists of repeated objects. 
For example, here is a list of users:
```json
{
  "users": [
    { "name": "Alice", "age": 20 }, 
    { "name": "Bob", "age": 30 }
  ]
}
```
To iterate over each user and print their name, use:
```php
$parser = \JsonDecodeStream\Parser::fromFile("users.json"); 
foreach ($parser->items("users[]") as $user) {
    echo $user->name; 
}
// or
foreach ($parser->items("users[].name") as $name) {
    echo $name; 
}
```

## Documentation

**json-decode-stream** uses layered generators to process data. 
There are 3 layers, and the processing goes as follows:

```
Tokenizer -(tokens)-> Parser -(events)-> Collector -(items)-> your code
```

- **Tokens** are parts of encoded JSON data: 
braces, comas, strings, numbers.
- **Events** are emitted based on sequence of incoming tokens: 
object started/ended, key specified, value, etc.
- **Items** are final parts of decoded JSON: scalar values, 
arrays and objects, that are matched by **selectors**.

`Parser` class provides access to each layer directly, 
but for most use cases only `$parser->items($selector)` is needed.

### Selectors

Selector is a string that specifies full path to collected JSON fields.
Simple selectors are:
- `[]` - any element of an array or every key of an object 
- `[5]` - element of an array with index 5
- `[10:15]` - any element of an array with index ranged from 10 to 15
- `[5:]` - any element of an array starting from index 5
- `[:5]` - any element of an array before and including index 5
- `foo` - value of an object by key 'foo'
- `["some long key with spaces or \"quotes\" in it"]` - also a value by key

Selectors can be nested:
- `users[]` would select each user
- `result.total` would select key "total" in "result" object
- `result[]` would select value of "total" and any other fields of "result" object
- `users[:2].name` would select names of first 3 users
- `[].id` would select every ID in the top-level array of objects with "id" field

### Reference

#### Parser
Constructors:
- `Parser::fromString($jsonString)`

- `Parser::fromFile($filePath)`

- `Parser::fromStream($resource)`

    Where `$resource` is any resource that supports `fread()`

- `Parser::fromPsr7($stream)`

    Where `$stream` is any PSR-7 compliant StreamInterface, i.e. `$psr7Request->getBody()`

- `new Parser(new SourceBuffer(new SourceInterfaceImplementation()))`

    Use in other custom cases

Methods:
- `$parser->tokens(): Generator<Token>`

    Iterates over encoded JSON document, returning `Token` objects.

- `$parser->events(): Generator<Event>` 

    Iterates over `tokens()`, returning `Event` objects.

- `$parser->items($selectors): Generator<scalar|object|array>`

    Iterates over `events()`, returning decoded JSON fields matched by selectors

    Where`$selectors` either
    - single selector string: `"result.users[]"`
    - coma-separated selector strings: `"result.total, result.users[]"`
    - array of selector strings: `[ "result.total", "result.users[]" ]`
    - custom CollectorInterface or array of them
    - `null` to collect whole objects/arrays in JSON-sequences (separated with coma or/and newline in source)

    String selectors are converted to `Collector` classes internally.

    For default Collector, iterated values have their full path in key, 
like `"result.users[4]" => ["num" => "Five", ..]` 

#### Event
Methods:

- `$event->getId(): string`
    
    Enumeration:
    
    * `Event::DOCUMENT_START`
    * `Event::DOCUMENT_END`
    * `Event::OBJECT_START`
    * `Event::OBJECT_END`
    * `Event::KEY`
    * `Event::VALUE`
    
- `$event->getValue(): string|number|bool|null`

   * For `Event::VALUE` a corresponding value is returned. 
   * For `Event::KEY` a string (field name of an object) is returned. 
   * For other events null is returned.
   
- `$event->getPath(): string`

    Full path to the currently parsed element.
   
- `$event->getDepth(): int`

    How many nested levels of JSON structure are we deep. Elements of top-level array/object have depth 1.
    
- `$event->matchPath(string $selector): bool`

    Checks if currenly parsed element's path is contained within selector.
    
- `$event->getLineNumber(): int` and `$event->getCharNumber(): int`

    Returns currently parsed position inside decoded source.
    
#### Token

- `$token->getId(): string`

    Enumeration:
    
    * `Token::OBJECT_START`
    * `Token::OBJECT_END`
    * `Token::ARRAY_START`
    * `Token::ARRAY_END`
    * `Token::KEY_DELIMITER`
    * `Token::COMA`
    * `Token::TRUE`
    * `Token::FALSE`
    * `Token::NULL`
    * `Token::STRING`
    * `Token::NUMBER`
    * `Token::WHITESPACE`
    
- `$token->getValue(): string|number|bool|null`

    Returns corresponding value only for `STRING`, `NUMBER` or `WHITESPACE` tokens.
    
- `$token->getLineNumber(): int` and `$token->getCharNumber(): int`

    Returns currently parsed position inside decoded source.
     

#### Custom Collectors

`CollectorInterface` defines only one method:

* `processEvent(Event $event)`

Return an array of `[ key, value ]` to be yielded from `items()` when you need to emit an item.

Yield multiple `[ key, value ]`s when you need to emit multiple items. 

Otherwise, return null if you have nothing yet to yield.

Here is an example of custom Collector:

```php
class AggregationCollector implements CollectorInterface
{
    protected int $count;
    protected float $sum;
    
    public function processEvent(Event $event)
    {
        switch ($event->getId()) {
            case Event::DOCUMENT_START:
                $this->count = 0;
                $this->sum = 0;
                break;
            
            case Event::VALUE:
                if ($event->matchPath("games[].score")) {
                    $this->sum += $event->getValue();
                    $this->count++;
                }
                break;

            case Event::DOCUMENT:END:
                yield [ 'count', $this->count ];
                yield [ 'sum', $this->sum ];
                yield [ 'avg', $this->count ? ($this->sum / $this->count) : 0 ];
                break;
        }
    }
}

$aggregates = iterator_to_array($parser->items(new AggregationCollector()));
var_dump($aggregates); // [ 'count' => 10, 'sum' => 50, 'avg' => 5 ]
``` 

## Dependencies

There are no external dependencies except `ext-json`, 
which is normally comes with every PHP distribution.

Default `json_decode` is used to parse single JSON strings when Parser finds them.
This is faster and more error-proof than writing own JSON string parser/validator. 
    
## Testing

This lib is heavily [covered](https://scrutinizer-ci.com/g/klkvsk/json-decode-stream/code-structure/master/code-coverage/src/)
with unit tests and [CI-tested](https://github.com/klkvsk/json-decode-stream/actions) under all versions of PHP since 7.1.

To run tests, install via composer with `--dev` and run 

```shell script
$ vendor/bin/phpunit
```   

## License

This code is distributed under [MIT license](./LICENSE.txt).
