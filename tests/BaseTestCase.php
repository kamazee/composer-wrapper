<?php

use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    const WRAPPER = '../composer';

    public function setUp()
    {
        $this->expectOutputWithShebang();
        require self::fullWrapperPath();
    }


    protected static function fullWrapperPath()
    {
        return realpath(__DIR__ . '/' . self::WRAPPER);
    }

    protected function expectOutputWithShebang($output = null)
    {
        $shebang = $this->getExpectedShebang();
        $this->expectOutputString($shebang . $output);
    }

    private function getExpectedShebang()
    {
        $wrapperFileLines = file(self::fullWrapperPath());
        return $wrapperFileLines[0];
    }

    protected static function callNonPublic($object, $method, $args)
    {
        $method = new ReflectionMethod($object, $method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    protected static function setNonPublic($object, $property, $arg)
    {
        $property = new ReflectionProperty($object, $property);
        $property->setAccessible(true);

        $property->setValue($object, $arg);
    }
}
