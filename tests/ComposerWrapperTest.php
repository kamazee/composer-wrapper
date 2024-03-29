<?php

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\MockObject\MockObject;
use BaseTestCase as TestCase;

class ComposerWrapperTest extends TestCase
{

    const WRAPPER_CLASS = 'ComposerWrapper';
    const INSTALLER = '<?php die("This is a stub and should never be executed");';


    private static function getInstance()
    {
        $class = self::WRAPPER_CLASS;
        return new $class;
    }

    /**
     * @test
     */
    public function runUsesCorrectDefaultDir()
    {
        $this->runCallsAllRequiredMethods(dirname(__DIR__));
    }

    /**
     * @test
     */
    public function runUsesDirFromEnvIfCorrect()
    {
        $self = $this;
        self::isolatedEnv(function () use ($self) {
            $self->runCallsAllRequiredMethods(__DIR__);
        }, array('COMPOSER_DIR' => __DIR__));
    }

    /**
     * @test
     */
    public function installsIfNotInstalled()
    {
        $dir = vfsStream::setup()->url();
        $filename = "$dir/composer.phar";
        /** @var MockObject|ComposerWrapper $mock */
        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('installComposer'))
            ->getMock();
        $mock->expects($this->once())
            ->method('installComposer')
            ->with($dir)
            ->willReturn(null);

        self::callNonPublic($mock, 'ensureInstalled', array($filename));
    }

    /**
     * @test
     * @dataProvider installsRequestedChannelExamples
     */
    public function installsRequestedChannel($dir, $supportsChannelFlag, $requestedChannel)
    {
        $params = new ComposerWrapperParams();
        $params->setChannel($requestedChannel);
        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('showError', 'copy', 'verifyChecksum', 'unlink', 'supportsChannelFlag'))
            ->setConstructorArgs(array($params))
            ->getMock();
        if ($supportsChannelFlag) {
            $showErrorExpectation = self::never();
        } else {
            $showErrorExpectation = self::once();
        }
        $wrapper->expects($showErrorExpectation)->method('showError');
        $wrapper->expects(self::once())->method('copy')->willReturn(true);
        $wrapper->expects(self::once())->method('verifyChecksum')->willReturn(null);
        $wrapper->expects(self::once())->method('unlink')->willReturn(null);
        $wrapper->expects(self::once())->method('supportsChannelFlag')->willReturn($supportsChannelFlag);
        self::callNonPublic(
            $wrapper,
            'installComposer',
            array($dir)
        );
    }

    public static function installsRequestedChannelExamples()
    {
        return array(
            'version 1 is requested; installed supports flag' => array(
                'dir' => __DIR__ . '/installer_stub_with_major_version_flags',
                'supportsChannelFlags' => true,
                'requestedVersion' => 1,
            ),
            'version 1 is requested; installed doesn\'t support flag' => array(
                'dir' => __DIR__ . '/installer_stub_without_major_version_flags',
                'supportsChannelFlags' => false,
                'requestedVersion' => 1,
            ),
        );
    }

    /**
     * @test
     */
    public function installWorksIfPhpIsInDirWithSpaces()
    {
        $dirWithSpaces = __DIR__ . '/directory with spaces';
        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('copy', 'verifyChecksum', 'getPhpBinary', 'unlink'))
            ->getMock();

        $wrapper->expects($this->once())->method('copy')->willReturn(true);
        $wrapper->expects($this->once())->method('verifyChecksum');
        $wrapper->expects($this->once())
            ->method('getPhpBinary')
            ->willReturn($dirWithSpaces . '/php');
        $wrapper->expects($this->once())->method('unlink');

        $installerPathName = $dirWithSpaces . '/composer-setup.php';
        $this->expectOutputString(
            "I was called with $installerPathName --install-dir=$dirWithSpaces"
        );

        self::callNonPublic($wrapper, 'installComposer', array($dirWithSpaces));
    }

    /**
     * @test
     */
    public function doesntTryToInstallWhenInstalled()
    {
        $vfs = vfsStream::setup();
        $dir = $vfs->url();
        $filename = "$dir/composer.phar";
        // Just "touch()" doesn't work for VFS until PHP 5.4
        file_put_contents($filename, '');
        /** @var MockObject|ComposerWrapper $mock */
        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('installComposer'))
            ->getMock();
        $mock->expects($this->never())
            ->method('installComposer');

        self::callNonPublic($mock, 'ensureInstalled', array($filename));
    }

    /**
     * @test
     * @dataProvider failedDownloadResultsProvider
     */
    public function throwsOnFailureToDownloadChecksum($downloadResult)
    {
        /** @var ComposerWrapper|MockObject $mock */
        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('file_get_contents', 'copy'))
            ->getMock();

        $installerFile = __DIR__ . '/' . ComposerWrapper::INSTALLER_FILE;
        $mock->expects($this->once())
            ->method('copy')
            ->with(ComposerWrapper::INSTALLER_URL, $installerFile)
            ->willReturn(true);

        $mock->expects($this->once())
            ->method('file_get_contents')
            ->with(ComposerWrapper::EXPECTED_INSTALLER_CHECKSUM_URL)
            ->willReturn($downloadResult);

        $this->expectExceptionMessageCompat('Exception', ComposerWrapper::MSG_ERROR_DOWNLOADING_CHECKSUM);

        $mock->installComposer(__DIR__);
    }

    public static function failedDownloadResultsProvider()
    {
        return array(
            'complete failure' => array(false),
            'a sudden nothing' => array(''),
        );
    }

    /**
     * @test
     */
    public function throwsOnFailureToDownloadInstaller()
    {
        $this->expectExceptionMessageCompat(
            'Exception',
            ComposerWrapper::MSG_ERROR_DOWNLOADING_INSTALLER
        );

        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('copy', 'validateChecksum'))
            ->getMock();

        $mock->expects($this->never())
            ->method('validateChecksum');

        $dir = __DIR__;
        $installerFile = $dir . DIRECTORY_SEPARATOR . ComposerWrapper::INSTALLER_FILE;
        $mock->expects($this->once())
            ->method('copy')
            ->with(ComposerWrapper::INSTALLER_URL, $installerFile)
            ->willReturn(false);

        self::callNonPublic($mock, 'installComposer', array($dir));
    }

    /**
     * @test
     */
    public function throwsOnInstallerChecksumMismatch()
    {
        $this->expectExceptionMessageCompat(
            'Exception',
            ComposerWrapper::MSG_ERROR_INSTALLER_CHECKSUM_MISMATCH
        );

        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('file_get_contents', 'copy'))
            ->getMock();

        $installer = '<?php echo "THIS PHP FILE SHOULD NOT HAVE BEEN EXECUTED"; exit(1);';
        $dir = vfsStream::setup()->url();

        $installerFile = "$dir/composer-setup.php";
        file_put_contents($installerFile, $installer);
        $realHash = hash('sha384', $installer);

        // Replacing last character with underscore definitely breaks it
        $brokenHash = substr($realHash, 0, -1) . '_';

        $mock->expects($this->once())
            ->method('file_get_contents')
            ->with(ComposerWrapper::EXPECTED_INSTALLER_CHECKSUM_URL)
            ->willReturn($brokenHash);

        $mock->expects($this->once())
            ->method('copy')
            ->with(ComposerWrapper::INSTALLER_URL, $installerFile)
            ->willReturn(true);

        try {
            self::callNonPublic($mock, 'installComposer', array($dir));
        } catch (Exception $e) {
            $this->assertFileNotExists($installerFile);
            throw $e;
        }
    }

    /**
     * @test
     */
    public function acceptsDownloadedChecksumWithLineFeed()
    {
        $this->expectOutputString('Installer was called and will succeed');

        $dir = __DIR__ . '/installer_success';
        $installerFile = $dir . DIRECTORY_SEPARATOR . ComposerWrapper::INSTALLER_FILE;

        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('file_get_contents', 'copy', 'unlink'))
            ->getMock();

        // Real downloaded checksum has an EOL character at the end
        $mock->expects($this->once())
            ->method('file_get_contents')
            ->with(ComposerWrapper::EXPECTED_INSTALLER_CHECKSUM_URL)
            ->willReturn(hash_file('sha384', $installerFile) . "\n");

        $mock->expects($this->once())
            ->method('copy')
            ->with(ComposerWrapper::INSTALLER_URL, $installerFile)
            ->willReturn(true);

        $mock->expects($this->once())
            ->method('unlink')
            ->with($installerFile)
            ->willReturn(true);

        self::callNonPublic($mock, 'installComposer', array($dir));
    }

    /**
     * @test
     */
    public function throwsOnInstallerFailure()
    {
        $this->expectOutputString('Installer was called and will return an error');
        $this->expectExceptionMessageCompat('Exception', ComposerWrapper::MSG_ERROR_WHEN_INSTALLING);

        $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('file_get_contents', 'copy', 'unlink'))
            ->getMock();

        $dir = __DIR__ . '/installer_failure';
        $installerFile = $dir . DIRECTORY_SEPARATOR . ComposerWrapper::INSTALLER_FILE;

        $mock->expects($this->once())
            ->method('file_get_contents')
            ->with(ComposerWrapper::EXPECTED_INSTALLER_CHECKSUM_URL)
            ->willReturn(hash_file('sha384', $installerFile));

        $mock->expects($this->once())
            ->method('copy')
            ->with(ComposerWrapper::INSTALLER_URL, $installerFile)
            ->willReturn(true);

        $mock->expects($this->once())
            ->method('unlink')
            ->with($installerFile)
            ->willReturn(true);

        self::callNonPublic($mock, 'installComposer', array($dir));
    }

    /**
     * @test
     * @dataProvider permissionsProvider
     */
    public function makesExecutable($permissionsBefore, $expectedPermissionsAfter)
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('At least PHP 5.4 is required to test chmod() on vfs');
        }

        $dir = vfsStream::setup()->url();
        $file = "$dir/composer.phar";
        file_put_contents($file, '');
        chmod($file, $permissionsBefore);
        $wrapper = new ComposerWrapper();
        self::callNonPublic($wrapper, 'ensureExecutable', array($file));
        // & 0777 grabs last 3 octal values, e.g. 0100644 -> 0644
        $this->assertEquals($expectedPermissionsAfter, fileperms($file) & 0777);
    }

    public static function permissionsProvider()
    {
        return array(
            'sets executable, does not add writable' => array(0444, 0555),
            'preserves writability' => array(0666, 0777),
            'preserves level of permissions' => array(0644, 0755),
            'does not change anything when already executable and read-only' => array(0555, 0555),
            'does not change anything when already executable and writable' => array(0777, 0777),
        );
    }

    /**
     * @test
     */
    public function triesToSelfUpdateWhenOutdated()
    {
        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('isUpToDate', 'selfUpdate', 'showError'))
            ->getMock();
        $wrapper->expects($this->once())->method('isUpToDate')->willReturn(false);
        $wrapper->expects($this->once())->method('selfUpdate');
        $wrapper->expects($this->never())->method('showError');

        self::callNonPublic($wrapper, 'ensureUpToDate', array(__FILE__));
    }

    /**
     * @test
     */
    public function selfUpdateWorks()
    {
        if (PHP_VERSION_ID < 50400) {
            $this->markTestSkipped('At least PHP 5.4 is required to use touch() on vfs');
        }

        $root = vfsStream::setup();
        $now = new DateTime();
        $composerLastModified = new DateTime('-7 days -1 minute');
        $file = vfsStream::newFile('composer.phar', 0755);
        $root->addChild($file);
        $file->lastModified($composerLastModified->getTimestamp());

        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('passthru'))
            ->getMock();
        $wrapper->expects($this->once())
            ->method('passthru')
            ->with("'{$file->url()}' 'self-update'", $this->anything())
            ->willReturnCallback(function ($command, &$exitCode) { $exitCode = 0; });

        self::callNonPublic($wrapper, 'selfUpdate', array($file->url()));
        clearstatcache(null, $file->url());
        $this->assertGreaterThanOrEqual($now->getTimestamp(), filemtime($file->url()));
    }

    /**
     * @test
     */
    public function detectsOutdated()
    {
        $root = vfsStream::setup();

        $composerLastModified = new DateTime('-7 days -1 minute');
        $file = vfsStream::newFile('composer.phar', 0755);
        $root->addChild($file);
        $file->lastModified($composerLastModified->getTimestamp());

        $this->assertFalse(self::callNonPublic(self::getInstance(), 'isUpToDate', array($file->url())));
    }

    /**
     * @test
     */
    public function selfUpdateWorksInDirectoryWithSpaces()
    {
        $dirWithSpaces = __DIR__ . '/directory with spaces';
        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('showError'))
            ->getMock();

        $wrapper->expects($this->never())->method('showError');
        $this->expectOutputString('I was called with self-update');

        self::callNonPublic($wrapper, 'selfUpdate', array($dirWithSpaces . '/composer.phar'));
    }

    /**
     * @test
     */
    public function doesNotTryToSelfUpdateWhenUpToDate()
    {
        $root = vfsStream::setup();
        $file = vfsStream::newFile('composer.phar', 0755);
        $root->addChild($file);

        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('passthru'))
            ->getMock();
        $wrapper->expects($this->never())
            ->method('passthru');

        self::callNonPublic($wrapper, 'ensureUpToDate', array($file->url()));
    }

    /**
     * @test
     * @dataProvider forceVersionsProvider
     * @param int $version
     * @param bool $supportsFlags
     * @param string $flag
     */
    public function addsForceVersionFlag($version, $supportsFlags, $flag, $expectError = false)
    {
        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('supportsChannelFlag', 'touch', 'passthru', 'showError'))
            ->getMock();

        $wrapper->expects($this->once())->method('supportsChannelFlag')->willReturn($supportsFlags);
        $wrapper->expects($this->once())->method('touch')->willReturn(null);
        $wrapper->expects($expectError ? $this->once() : $this->never())->method('showError');
        $self = $this;
        $wrapper->expects($this->once())->method('passthru')->with()
            ->willReturnCallback(
                function($command, &$exitCode) use ($self, $flag) {
                    $self->assertStringEndsWith(" 'self-update'" . (null === $flag ? '' : " '$flag'"), $command);
                    $exitCode = 0;
                }
            );

        $params = new ComposerWrapperParams();
        $params->setChannel($version);
        self::setNonPublic($wrapper, 'params', $params);

        self::callNonPublic($wrapper, 'selfUpdate', array(__FILE__));
    }

    public static function forceVersionsProvider()
    {
        return array(
            'Version 1 with forcing support' => array('version' => 1, 'supportsFlags' => true, 'flag' => '--1'),
            'Version 2 with forcing support' => array('version' => 2, 'supportsFlags' => true, 'flag' => '--2'),
            'Version 1 without forcing support' => array('version' => 1, 'supportsFlags' => false, 'flag' => '1.10.5'),
            'Version 2 without forcing support' => array('version' => 2, 'supportsFlags' => false, 'flag' => null, 'expectError' => true),
        );
    }

    /**
     * @test
     */
    public function printsWarningWhenUpdateFailsToSelfUpdate()
    {
        $file = __FILE__;
        $wrapper = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods(array('passthru', 'showError'))
            ->getMock();
        $wrapper->expects($this->once())
            ->method('passthru')
            ->with("'$file' 'self-update'", $this->anything())
            ->willReturnCallback(function ($command, &$exitCode) { $exitCode = 1; });
        $wrapper->expects($this->once())
            ->method('showError')
            ->with(ComposerWrapper::MSG_SELF_UPDATE_FAILED);

        self::callNonPublic($wrapper, 'selfUpdate', array($file));
    }

    /**
     * @test
     */
    public function composerExceptionProcessed()
    {
        $root = vfsStream::setup();
        $composerDir = $root->url();
        $composer = vfsStream::newFile('composer.phar', 0755);
        $root->addChild($composer);
        $expectedExceptionText = 'Expected exception text';
        $content = sprintf('<?php throw new Exception("%s");', $expectedExceptionText);
        $composer->setContent($content);

        $this->expectExceptionMessageCompat('Exception', $expectedExceptionText);
        $self = $this;
        self::isolatedEnv(function () use ($composerDir, $self) {
            $self->runCallsAllRequiredMethods($composerDir, false);
        }, array('COMPOSER_DIR' => $composerDir));
    }

    public function runCallsAllRequiredMethods($expectedComposerDir, $mockDelegate = true)
    {
        $methods = array('ensureInstalled', 'ensureExecutable', 'ensureUpToDate');
        if ($mockDelegate) {
            $methods[] = 'delegate';
        }

        /** @var MockObject|ComposerWrapper $runnerMock */
        $runnerMock = $this->getMockBuilder(self::WRAPPER_CLASS)
            ->setMethods($methods)
            ->getMock();

        foreach ($methods as $method) {
            $runnerMock->expects($this->once())
                ->method($method)
                ->with("$expectedComposerDir/composer.phar")
                ->willReturn(null);
        }

        $runnerMock->run(array());
    }

    /**
     * @test
     */
    public function passThroughWrapperWorksWithReferences()
    {
        $testScriptPath = 'passthru/error.php';
        $testScriptAbsPath = __DIR__ . '/' . $testScriptPath;
        $this->expectOutputString("$testScriptPath was executed");
        $wrapper = new ComposerWrapper();
        $exitCode = null;
        $class = new ReflectionClass($wrapper);
        $method = $class->getMethod('passthru');
        $method->setAccessible(true);
        $method->invokeArgs(
            $wrapper,
            array(
                $wrapper->getPhpBinary() . ' ' . escapeshellarg($testScriptAbsPath),
                &$exitCode
            )
        );
        $this->assertEquals(1, $exitCode);
    }

    /**
     * @test
     * @dataProvider detectSelfUpdateDataProvider
     * @param array $arguments
     * @param bool $expected
     */
    public function detectsSelfUpdate($arguments, $expected)
    {
        $wrapper = new ComposerWrapper();
        $actual = self::callNonPublic($wrapper, 'isSelfUpdate', array($arguments));
        self::assertSame($expected, $actual);
    }

    public static function detectSelfUpdateDataProvider()
    {
        return array(
            'selfupdate' => array(array('selfupdate'), true),
            'self-update' => array(array('self-update'), true),
            '-v selfupdate' => array(array('-v', 'selfupdate'), true),
            '-v self-update' => array(array('-v', 'self-update'), true),
            'no arguments' => array(array(), false),
            'install' => array(array('install'), false),
            '-v install' => array(array('-v', 'install'), false),
        );
    }

    /**
     * @test
     */
    public function passesThruOnSelfUpdate()
    {
        $mock = $this->getMockBuilder('ComposerWrapper')
            ->setMethods(array('runByRealComposerSubprocess', 'delegate', 'ensureInstalled', 'ensureExecutable'))
            ->getMock();
        $mock->expects(self::once())->method('ensureInstalled')->willReturn(true);
        $mock->expects(self::once())->method('ensureExecutable')->willReturn(true);
        $mock->expects(self::never())->method('delegate');
        $mock->expects(self::once())->method('runByRealComposerSubprocess')->willReturn(0);
        $exitCode = $mock->run(array('self-update'));
        self::assertSame(0, $exitCode);
    }

    /**
     * @test
     * @dataProvider selfUpdateHelpProvider
     * @param string $helpOutput
     * @param array $versions
     * @param bool $expectedResult
     */
    public function detectsSupportForForceVersionFlags($helpOutput, $versions, $expectedResult)
    {
        foreach ($versions as $version) {
            $mock = $this->getMockBuilder(self::WRAPPER_CLASS)
                ->setMethods(array('getCliCallOutput'))
                ->getMock();

            $mock->expects($this->once())
                ->method('getCliCallOutput')
                ->willReturn($helpOutput);

            $this->assertSame(
                $expectedResult,
                self::callNonPublic($mock, 'supportsChannelFlag', array(array('composer'), $version))
            );
        }
    }

    public static function selfUpdateHelpProvider()
    {
        return array(
            'self_update_without_version_flags' => array(
                'helpOutput' => file(__DIR__ . '/self-update_help_examples/without_version_flags.txt'),
                'versions' => array(1, 2),
                'expectedResult' => false
            ),
            'self_update_with_version_flags' => array(
                'helpOutput' => file(__DIR__ . '/self-update_help_examples/with_version_flags.txt'),
                'versions' => array(1, 2),
                'expectedResult' => true,
            ),
            'self_update_with_version_flags_without_3' => array(
                'helpOutput' => file(__DIR__ . '/self-update_help_examples/with_version_flags.txt'),
                'versions' => array(3),
                'expectedResult' => false,
            ),
            'installer_with_major_version_flag' => array(
                'helpOutput' => file(__DIR__ . '/installer_stub_with_major_version_flags/help_output.txt'),
                'versions' => array(1, 2),
                'expectedResult' => true,
            ),
            'installer_with_major_version_flag_wrong_version' => array(
                'helpOutput' => file(__DIR__ . '/installer_stub_with_major_version_flags/help_output.txt'),
                'versions' => array(3),
                'expectedResult' => false,
            ),
            'installer_without_major_version_flag' => array(
                'helpOutput' => file(__DIR__ . '/installer_stub_without_major_version_flags/help_output.txt'),
                'versions' => array(1, 2),
                'expectedResult' => false,
            )
        );
    }
}
