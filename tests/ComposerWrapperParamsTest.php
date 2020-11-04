<?php

require_once __DIR__ . '/BaseTestCase.php';

use BaseTestCase as TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;

class ComposerWrapperParamsTest extends TestCase
{
    const COMPOSER_WRAPPER_PARAMS = 'ComposerWrapperParams';

    public function setUp()
    {
        parent::setUp();
        self::assertTrue(class_exists(self::COMPOSER_WRAPPER_PARAMS));
        vfsStream::setup('/root');
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
            "float number, strange but supported by php engine" => array(1.5, "1.5"),
            "negative integer, strange but supported by php engine" => array(-100, "-100"),
        );
    }

    /**
     * @test
     * @dataProvider updateFreqGoodDataProvider
     */
    public function updateFreqLoadWellFromEnv($input, $expected)
    {
        $params = $this->loadParamsFromEnv(array('COMPOSER_UPDATE_FREQ' => $input));

        self::assertSame($expected, $params->getUpdateFreq());
    }

    /**
     * @test
     * @dataProvider updateFreqGoodDataProvider
     */
    public function updateFreqLoadWellFromJson($input, $expected)
    {
        $params = $this->loadParamsFromJson(array("update-freq" => $input));

        self::assertSame($expected, $params->getUpdateFreq());
    }

    public function updateFreqBadDataProvider()
    {
        return array(
            "positive integer value" => array(100),
            "zero" => array(0),
            "empty string" => array(''),
        );
    }

    /**
     * @test
     * @dataProvider updateFreqBadDataProvider
     */
    public function updateFreqLoadBadFromEnv($input)
    {
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong update frequency is requested: .*/');
        $this->loadParamsFromEnv(array('COMPOSER_UPDATE_FREQ' => $input));
    }

    /**
     * @test
     * @dataProvider updateFreqBadDataProvider
     */
    public function updateFreqLoadBadFromJson($input)
    {
        $this->skipIfFloatInJsonUnsupported($input);
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong update frequency is requested: .*/');
        $this->loadParamsFromJson(array("update-freq" => $input));
    }

    /**
     * @test
     */
    public function forceMajorVersionLoadDefault()
    {
        $params = $this->loadParamsDefault();
        self::assertSame(false, $params->getForceMajorVersion());
    }

    public function forceMajorVersionGoodDataProvider()
    {
        return array(
            "1 as int" => array(1, 1),
            "1 as string" => array("1", 1),
            "2 as int" => array(2, 2),
            "2 as string" => array("2", 2),
        );
    }

    /**
     * @test
     * @dataProvider forceMajorVersionGoodDataProvider
     */
    public function forceMajorVersionLoadWellFromEnv($input, $output)
    {
        $params = $this->loadParamsFromEnv(array('COMPOSER_FORCE_MAJOR_VERSION' => $input));

        self::assertSame($output, $params->getForceMajorVersion());
    }

    /**
     * @test
     * @dataProvider forceMajorVersionGoodDataProvider
     */
    public function forceMajorVersionLoadWellFromJson($input, $output)
    {
        $params = $this->loadParamsFromJson(array("major-version" => $input));

        self::assertSame($output, $params->getForceMajorVersion());
    }

    public function forceMajorVersionBadDataProvider()
    {
        return array(
            "negative" => array(-1),
            "allowed versions but not in float" => array(1.0),
            "positive more than 2 " => array(3),
            "zero " => array(0),
        );
    }

    /**
     * @test
     * @dataProvider forceMajorVersionBadDataProvider
     */
    public function forceMajorVersionLoadBadFromEnv($input)
    {
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong major version is requested:.*/');

        $this->loadParamsFromEnv(array('COMPOSER_FORCE_MAJOR_VERSION' => $input));
    }

    /**
     * @test
     * @dataProvider forceMajorVersionBadDataProvider
     */
    public function forceMajorVersionLoadBadFromJson($input)
    {
        $this->skipIfFloatInJsonUnsupported($input);
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong major version is requested:.*/');
        $this->loadParamsFromJson(array("major-version" => $input));
    }

    /**
     * @test
     */
    public function composerDirLoadDefault()
    {
        $params = $this->loadParamsDefault();
        self::assertSame(dirname(self::fullWrapperPath()), $params->getComposerDir());
    }

    public function composerDirGoodDataProvider()
    {
        return array(
            "project dir" => array(__DIR__, __DIR__),
            "dir in other place" => array(
                vfsStream::url('root/UniqNameEnvDirectory'),
                'vfs://root/UniqNameEnvDirectory',
            ),
        );
    }

    /**
     * @test
     * @dataProvider composerDirGoodDataProvider
     */
    public function composerDirLoadWellFromEnv($input, $expected)
    {
        if (!file_exists($input)) {
            mkdir($input);
        }
        $params = $this->loadParamsFromEnv(array('COMPOSER_DIR' => $input));
        self::assertSame($expected, $params->getComposerDir());
    }

    /**
     * @test
     * @dataProvider composerDirGoodDataProvider
     */
    public function composerDirLoadWellFromJson($input, $expected)
    {
        if (!file_exists($input)) {
            mkdir($input);
        }
        $params = $this->loadParamsFromJson(array("composer-dir" => $input));
        self::assertSame($expected, $params->getComposerDir());
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
    public function composerDirLoadBadFromEnv($input)
    {
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong composer dir is requested:.*/');
        $this->loadParamsFromEnv(array('COMPOSER_DIR' => $input));
    }

    /**
     * @test
     * @dataProvider composerDirBadDataProvider
     */
    public function composerDirLoadBadFromJson($input)
    {
        $this->expectExceptionMessageRegExpCompat('\Exception', '/Wrong composer dir is requested:.*/');
        $this->loadParamsFromJson(array("composer-dir" => $input));
    }

    /**
     * @return ComposerWrapperParams
     */
    private function loadParamsDefault()
    {
        $params = new ComposerWrapperParams($this->isolatedFolder());
        $params->loadReal();

        return $params;
    }

    /**
     * @param array $env
     * @return ComposerWrapperParams
     * @throws Exception
     */
    private function loadParamsFromEnv(array $env)
    {
        $params = new ComposerWrapperParams($this->isolatedFolder());
        self::isolatedEnv(
            $env,
            function () use ($params) {
                $params->loadReal();
            }
        );

        return $params;
    }

    /**
     * @param array $config
     * @return ComposerWrapperParams
     */
    private function loadParamsFromJson(array $config)
    {
        $path = $this->folderWithJsonWithWrapperConfig(
            $config
        );
        $params = new ComposerWrapperParams($path);
        $params->loadReal();

        return $params;
    }

    /**
     * @param array $wrapperConfig
     * @return string
     */
    private function folderWithJsonWithWrapperConfig(array $wrapperConfig)
    {
        $json = vfsStream::url('root/composer.json');

        $options = 0;
        if (null !== constant('JSON_PRESERVE_ZERO_FRACTION')) {
            $options = JSON_PRESERVE_ZERO_FRACTION;
        }
        $configWithNonDefaultValues = json_encode(array("config" => array("wrapper" => $wrapperConfig)), $options);
        file_put_contents($json, $configWithNonDefaultValues);

        return vfsStream::url('root');
    }

    /**
     * @return string
     */
    private function isolatedFolder()
    {
        return vfsStream::url('root');
    }

    private function skipIfFloatInJsonUnsupported($input)
    {
        if (PHP_VERSION_ID < 50606 && is_float($input)) {
            self::markTestSkipped(
                "Test skipped, because JSON_PRESERVE_ZERO_FRACTION available only since PHP 5.6.6 See https://www.php.net/manual/en/json.constants"
            );
        }
    }
}
