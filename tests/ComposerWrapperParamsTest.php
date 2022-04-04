<?php

use BaseTestCase as TestCase;
use org\bovigo\vfs\vfsStream;

class ComposerWrapperParamsTest extends TestCase
{
    const COMPOSER_WRAPPER_PARAMS = 'ComposerWrapperParams';

    /**
     * @test
     */
    public function envVariablesHasBiggerPriority()
    {
        $params = new ComposerWrapperParams();
        self::isolatedEnv(
            function () use ($params) {
                $params->loadReal();
            },
            array(
                "COMPOSER_UPDATE_FREQ" => "101 days",
                "COMPOSER_FORCE_MAJOR_VERSION" => 2,
                "COMPOSER_DIR" => 'dir_from_env',
            ),
            __DIR__ . '/composer.json_read_config_examples/envVariablesHasBiggerPriority'
        );

        self::assertSame("101 days", $params->getUpdateFreq());
        self::assertSame("2", $params->getForceMajorVersion());
        self::assertSame("dir_from_env", $params->getComposerDir());
    }

    /**
     * @test
     */
    public function composerJsonFromEnvVarIsRead()
    {
        $params = null;
        self::isolatedEnv(
            function () use (&$params) {
                $params = new ComposerWrapperParams();
                $params->loadReal();
            },
            array(
                'COMPOSER' => __DIR__ . '/composer.json_read_config_examples/composer.json_is_ignored_when_overridden/composer-from-env.json',
            ),
            __DIR__ . '/composer.json_read_config_examples/composer.json_is_ignored_when_overridden'
        );

        self::assertSame('101 days', $params->getUpdateFreq());
        self::assertSame('2', $params->getForceMajorVersion());
        self::assertSame('dir_from_composer-from-env.json', $params->getComposerDir());
    }

    /**
     * @test
     */
    public function updateFreqLoadDefault()
    {
        $params = $this->loadParamsDefault();
        self::assertSame('7 days', $params->getUpdateFreq());
    }

    public function updateFreqGoodDataProvider()
    {
        return array(
            "some value" => array("40 days", "40 days"),
            "negative integer, strange but supported by php engine" => array(-100, "-100"),
        );
    }

    /**
     * @test
     * @dataProvider updateFreqGoodDataProvider
     */
    public function setUpdateFreqHandlesGoodValue($input, $expected)
    {
        $params = new ComposerWrapperParams();
        self::callNonPublic($params, 'setUpdateFreq', array($input));
        $actual = $params->getUpdateFreq();
        self::assertSame($expected, $actual);
    }

    public function updateFreqBadDataProvider()
    {
        return array(
            "positive integer value" => array(100),
            "negative DateTime modifier" => array("-1 day"),
            "zero" => array(0),
            "empty string" => array(''),
        );
    }

    /**
     * @test
     * @dataProvider updateFreqBadDataProvider
     */
    public function setUpdateFreqThrowsOnBadValues($input)
    {
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong update frequency is requested: .*/');
        $params = new ComposerWrapperParams();
        self::callNonPublic($params, 'setUpdateFreq', array($input));
    }

    /**
     * @test
     */
    public function forceMajorVersionLoadDefault()
    {
        $params = $this->loadParamsDefault();
        self::assertNull($params->getForceMajorVersion());
    }


    /**
     * @test
     */
    public function composerDirLoadDefault()
    {
        $params = $this->loadParamsDefault();
        self::assertSame(dirname(self::fullWrapperPath()), $params->getComposerDir());
    }

    /**
     * @test
     */
    public function setComposerDirHandlesGoodValues()
    {
        $params = new ComposerWrapperParams();
        self::callNonPublic($params, 'setComposerDir', array(__DIR__));
        $actual = $params->getComposerDir();
        self::assertSame(__DIR__, $actual);
    }

    public function composerDirBadDataProvider()
    {
        return array(
            "access denied" => array('/var'),
            "doesn't exists" => array(__DIR__ . '/i_dont_exist'),
            "non dir" => array(__FILE__),
        );
    }

    /**
     * @test
     * @dataProvider composerDirBadDataProvider
     */
    public function setComposerDirThrowsOnBadValues($input)
    {
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong composer dir is requested:.*/');
        $params = new ComposerWrapperParams();
        self::callNonPublic($params, 'setComposerDir', array($input));
    }

    /**
     * @return ComposerWrapperParams
     */
    private function loadParamsDefault()
    {
        return self::isolatedEnv(
            function() {
                $params = new ComposerWrapperParams();
                $params->loadReal();

                return $params;
            },
            array(),
            __DIR__ . '/composer.json_read_config_examples/no_composer.json_here'
        );
    }
}
