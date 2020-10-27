<?php

require_once __DIR__ . '/BaseTestCase.php';

use BaseTestCase as TestCase;

class ComposerWrapperParamsTest extends TestCase
{
    const COMPOSER_WRAPPER_PARAMS = 'ComposerWrapperParams';

    public function setUp()
    {
        parent::setUp();
        self::assertTrue(class_exists(self::COMPOSER_WRAPPER_PARAMS));
    }

    /**
     * @test
     */
    public function loadFromEnv()
    {
        $unpredictableValues = array(
            'COMPOSER_UPDATE_FREQ' => -100,
        );
        $params = new ComposerWrapperParams();
        foreach ($unpredictableValues as $name => $value) {
            putenv($name . '=' . $value);
        }

        $params->loadReal();

        self::assertEquals(-100, $params->getUpdateFreq());
    }
}
