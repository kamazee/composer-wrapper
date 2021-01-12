<?php

use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    const WRAPPER = '../composer';

    protected static function fullWrapperPath()
    {
        return realpath(__DIR__ . '/' . self::WRAPPER);
    }

    private function getExpectedShebang()
    {
        $wrapperFileLines = file(self::fullWrapperPath());
        return $wrapperFileLines[0];
    }

    public static function callNonPublic($object, $method, $args)
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

    protected static function isolatedEnv(array $env, $callback)
    {
        // php 5.3 doesn't support callable pseudo type hint :(
        if (!is_callable($callback)) {
            throw new \Exception(
                sprintf("%s should be callable; got %s", '$callback', gettype($callback))
            );
        }
        foreach ($env as $name => $value) {
            putenv($name . '=' . self::convertToStringIfFloat($value));
        }
        try {
            $callback();
        } catch (Exception $e) {

        }
        foreach ($env as $name => $value) {
            putenv($name);
        }

        if (isset($e)) {
            throw $e;
        }
    }

    protected static function convertToStringIfFloat($value)
    {
        if (is_float($value)) {
            //float value 1.0 should be passed as "1.0" string but not "1"
            $value = number_format($value, 1, '.', '');
        }

        return $value;
    }

    protected function expectExceptionMessageCompat($class, $message)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($class);
        }

        if (method_exists($this, 'expectExceptionMessage')) {
            $this->expectExceptionMessage($message);
        } elseif (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException($class, $message);
        }
    }

    protected function expectExceptionMessageRegExpCompat($class, $regExp)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($class);
        }

        if (method_exists($this, 'expectExceptionMessageMatches')) {
            $this->expectExceptionMessageMatches($regExp);
        } elseif (method_exists($this, 'expectExceptionMessage')) {
            $this->expectExceptionMessageRegExp($regExp);
        } elseif (method_exists($this, 'setExpectedException')) {
            $this->setExpectedExceptionRegExp($class, $regExp);
        }
    }
}
