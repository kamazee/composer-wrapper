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
        $envValues = array(
            'COMPOSER_UPDATE_FREQ' => -100,
            'COMPOSER_FORCE_MAJOR_VERSION' => "1",
            'COMPOSER_DIR' => __DIR__
        );
        $params = new ComposerWrapperParams();
        self::isolatedEnv($envValues, function () use ($params) {
            $params->loadReal();
        });

        self::assertSame('-100', $params->getUpdateFreq());
        self::assertSame(1, $params->getForceMajorVersion());
        self::assertSame(__DIR__, $params->getComposerDir());
    }

    protected static function isolatedEnv($env, callable $callback)
    {
        foreach ($env as $name => $value) {
            putenv($name . '=' . $value);
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

    public function updateFreqValues()
    {
        return array(
            "default" => array(null, '7 days'),
            "some value" => array("40 days", "40 days"),
            "some strange value, but supported with php engine" => array(-100, "-100"),

        );
    }

    /**
     * @test
     * @dataProvider updateFreqValues
     */
    public function updateFreqParamCorrectValue($input, $expected)
    {
        $params = new ComposerWrapperParams();
        if (null !== $input) {
            $params->setUpdateFreq($input);
        }
        self::assertSame($expected, $params->getUpdateFreq());
    }

    public function wrongUpdateFreqValues()
    {
        return array(
            "integer is wrong value" => array(15),
            "empty string" => array(''),
        );
    }

    /**
     * @test
     * @dataProvider wrongUpdateFreqValues
     */
    public function negativeValidationUpdateFreqParam($input)
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessageRegExp('/Wrong update frequency is requested: .*/');
        $params = new ComposerWrapperParams();
        $params->setUpdateFreq($input);
    }

    public function forceMajorVersionValues()
    {
        return array(
            "false is default" => array(null, false),
            "1 as int" => array(1, 1),
            "1 as string" => array("1", 1),
            "2 as int" => array(2, 2),
            "2 as string" => array("2", 2),
        );
    }
    /**
     * @test
     * @dataProvider forceMajorVersionValues
     */
    public function forceMajorVersionParamCorrectValues($input, $output)
    {
        $params = new ComposerWrapperParams();
        if (null !== $input) {
            $params->setForceMajorVersion($input);
        }
        self::assertSame($output, $params->getForceMajorVersion());
    }

    public function wrongForceMajorVersionValues()
    {
        return array(
            "negative" => array(-1),
            "allowed versions but float" => array(1.0),
            "positive more than 2 " => array(3),
            "zero " => array(0),
        );
    }

    /**
     * @test
     * @dataProvider wrongForceMajorVersionValues
     */
    public function negativeValidationForceMajorVersionParam($input)
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessageRegExp('/Wrong major version is requested:.*/');
        $params = new ComposerWrapperParams();
        $params->setForceMajorVersion($input);
    }

    public function composerDirValues()
    {
        return array(
            "default path" => array(null, dirname(self::fullWrapperPath())),
            "current dir" => array(__DIR__, __DIR__),
        );
    }

    /**
     * @test
     * @dataProvider composerDirValues
     */
    public function composerDirCorrectValues($input, $expected)
    {
        $params = new ComposerWrapperParams();
        if (null !== $input) {
            $params->setComposerDir($input);
        }
        self::assertSame($expected, $params->getComposerDir());
    }

    public function wrongComposerDirValues()
    {
        return array(
            "access denied" => array('/var'),
            "doesn't exists" => array(__DIR__ . '/i_dont_exist'),
            "non dir" => array(__FILE__),
        );
    }

    /**
     * @test
     * @dataProvider wrongComposerDirValues
     */
    public function negativeValidationComposerDirParam($input)
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessageRegExp('/Wrong composer dir is requested:.*/');
        $params = new ComposerWrapperParams();
        $params->setComposerDir($input);
    }


}
