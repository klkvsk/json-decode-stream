<?php
declare(strict_types=1);

namespace JsonDecodeStream\Tests;


use JsonDecodeStream\Event;
use JsonDecodeStream\Parser;

class ParserTest extends Test
{
    /**
     * @dataProvider samples
     * @param $sampleFile
     * @throws \JsonDecodeStream\Exception\ParserException
     * @throws \JsonDecodeStream\Exception\TokenizerException
     */
    public function testSamples($sampleFile)
    {
        $parser = Parser::fromFile($sampleFile);
        $root = null;
        $current = null;
        $stack = [];
        $lastKey = null;

        $isStarted = false;
        $isFinished = false;
        foreach ($parser->events() as $event) {
            $this->assertFalse($isFinished, 'got event after document end');
            switch ($event->getId()) {
                case Event::DOCUMENT_START:
                    $isStarted = true;
                    break;
                case Event::DOCUMENT_END:
                    $this->assertTrue($isStarted);
                    $isFinished = true;
                    break;
                case Event::OBJECT_START:
                case Event::ARRAY_START:
                    if ($current !== null) {
                        $stack []= [ $current, $lastKey ];
                    }
                    $current = [];
                    $lastKey = null;
                    break;
                case Event::OBJECT_END:
                case Event::ARRAY_END:
                    $this->assertNotNull($current);
                    if (count($stack) > 0) {
                        $object = $current;
                        [ $current, $lastKey ] = array_pop($stack);
                        if ($lastKey !== null) {
                            $current[$lastKey] = $object;
                            $lastKey = null;
                        } else {
                            $current []= $object;
                        }
                    } else {
                        $root = $current;
                    }
                    break;
                case Event::KEY:
                    $this->assertNull($lastKey);
                    $lastKey = $event->getValue();
                    break;
                case Event::VALUE;
                    $this->assertNotNull($current);
                    if ($lastKey !== null) {
                        $this->assertArrayNotHasKey($lastKey, $current, 'duplicated key: ' . $lastKey);
                        $current[$lastKey] = $event->getValue();
                        $lastKey = null;
                    } else {
                        $current []= $event->getValue();
                    }
                    break;
            }
        }

        $expected = json_decode(file_get_contents($sampleFile), true);
        $this->assertNotNull($expected, json_last_error_msg());
        $this->assertEquals($expected, $root);
    }

    public function samples()
    {
        yield from $this->getSampleFiles();
    }

}
