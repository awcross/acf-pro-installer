<?php

namespace PivvenIT\Composer\Installers\ACFPro\Test;

use Composer\Config;
use Composer\Plugin\PluginEvents;
use PHPUnit\Framework\TestCase;
use PivvenIT\Composer\Installers\ACFPro\ACFProInstallerPlugin;
use PivvenIT\Composer\Installers\ACFPro\Exceptions\MissingKeyException;
use PivvenIT\Composer\Installers\ACFPro\LicenseKey\Providers\EnvironmentVariableLicenseKeyProvider;

class ACFProInstallerPluginTest extends TestCase
{
    const REPO_NAME = 'advanced-custom-fields/advanced-custom-fields-pro';
    const REPO_TYPE = 'wordpress-plugin';
    const REPO_URL =
        'https://connect.advancedcustomfields.com/index.php?p=pro&a=download';

    private static $actualWorkingDirectory;

    public static function setUpBeforeClass(): void
    {
        self::$actualWorkingDirectory = getcwd();
        $testId = uniqid("acf-pro-installer-test");
        $tempWorkDir = sys_get_temp_dir() . "/{$testId}";
        mkdir($tempWorkDir);
        chdir($tempWorkDir);
    }

    public static function tearDownAfterClass(): void
    {
        chdir(self::$actualWorkingDirectory);
    }

    protected function tearDown(): void
    {
        // Unset the environment variable after every test
        // See: http://stackoverflow.com/a/34065522
        putenv(EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME);

        // Delete the .env file
        $dotenv = getcwd() . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($dotenv)) {
            unlink($dotenv);
        }
    }

    public function testImplementsPluginInterface()
    {
        $this->assertInstanceOf(
            'Composer\Plugin\PluginInterface',
            new ACFProInstallerPlugin()
        );
    }

    public function testImplementsEventSubscriberInterface()
    {
        $this->assertInstanceOf(
            'Composer\EventDispatcher\EventSubscriberInterface',
            new ACFProInstallerPlugin()
        );
    }

    public function testSubscribesToPreFileDownloadEvent()
    {
        $subscribedEvents = ACFProInstallerPlugin::getSubscribedEvents();
        $this->assertEquals(
            $subscribedEvents[PluginEvents::PRE_FILE_DOWNLOAD],
            'addKey'
        );
    }

    public function testAddKeyCreatesCustomFilesystemWithOldValues()
    {
        // Make key available in the ENVIRONMENT
        putenv(EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME . '=KEY');

        // Mock a RemoteFilesystem
        $options = ['options' => 'array'];
        $tlsDisabled = true;

        $rfs = $this
            ->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->disableOriginalConstructor()
            ->setMethods(['getOptions', 'isTlsDisabled'])
            ->getMock();

        $rfs
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);

        $rfs
            ->expects($this->once())
            ->method('isTlsDisabled')
            ->willReturn($tlsDisabled);

        // Mock Config
        $config = $this
            ->getMockBuilder('Composer\Config')
            ->getMock();

        // Mock Composer
        $composer = $this
            ->getMockBuilder('Composer\Composer')
            ->setMethods(['getConfig'])
            ->getMock();

        $composer
            ->method('getConfig')
            ->willReturn($config);

        // Mock IOInterface
        $io = $this
            ->getMockBuilder('Composer\IO\IOInterface')
            ->getMock();

        // Mock an Event
        $event = $this
            ->getMockBuilder('Composer\Plugin\PreFileDownloadEvent')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getProcessedUrl',
                    'getRemoteFilesystem',
                    'setRemoteFilesystem'
                ]
            )
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn(self::REPO_URL);

        $event
            ->expects($this->once())
            ->method('getRemoteFilesystem')
            ->willReturn($rfs);

        $event
            ->expects($this->once())
            ->method('setRemoteFilesystem')
            ->with(
                $this->callback(
                    function ($rfs) use ($config, $io, $options, $tlsDisabled) {
                        $this->assertEquals($options, $rfs->getOptions());
                        $this->assertEquals($tlsDisabled, $rfs->isTlsDisabled());
                        return true;
                    }
                )
            );

        // Call addKey
        $plugin = new ACFProInstallerPlugin();
        $plugin->activate($composer, $io);
        $plugin->addKey($event);
    }

    public function testAddKeyFromENV()
    {
        // The key that should be available in the ENVIRONMENT
        $key = 'ENV_KEY';

        // Make key available in the ENVIRONMENT
        putenv(EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME . '=' . $key);

        // Mock a RemoteFilesystem
        $rfs = $this
            ->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->disableOriginalConstructor()
            ->setMethods(['getOptions', 'isTlsDisabled'])
            ->getMock();

        $rfs
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);

        $rfs
            ->expects($this->once())
            ->method('isTlsDisabled')
            ->willReturn(true);

        // Mock Config
        $config = $this
            ->getMockBuilder('Composer\Config')
            ->getMock();

        // Mock Composer
        $composer = $this
            ->getMockBuilder('Composer\Composer')
            ->setMethods(['getConfig'])
            ->getMock();

        $composer
            ->method('getConfig')
            ->willReturn($config);

        // Mock IOInterface
        $io = $this
            ->getMockBuilder('Composer\IO\IOInterface')
            ->getMock();

        // Mock an Event
        $event = $this
            ->getMockBuilder('Composer\Plugin\PreFileDownloadEvent')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getProcessedUrl',
                    'getRemoteFilesystem',
                    'setRemoteFilesystem'
                ]
            )
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn(self::REPO_URL);

        $event
            ->expects($this->once())
            ->method('getRemoteFilesystem')
            ->willReturn($rfs);

        $event
            ->expects($this->once())
            ->method('setRemoteFilesystem')
            ->with(
                $this->callback(
                    function ($rfs) use ($key) {
                        return true;
                    }
                )
            );

        // Call addKey
        $plugin = new ACFProInstallerPlugin();
        $plugin->activate($composer, $io);
        $plugin->addKey($event);
    }

    public function testAddKeyFromDotEnv()
    {
        // The key that should be available in the .env file
        $key = 'DOT_ENV_KEY';

        // Make key available in the .env file
        file_put_contents(
            getcwd() . DIRECTORY_SEPARATOR . '.env',
            EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME . '=' . $key
        );

        // Mock a RemoteFilesystem
        $rfs = $this
            ->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->disableOriginalConstructor()
            ->setMethods(['getOptions', 'isTlsDisabled'])
            ->getMock();

        $rfs
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);

        $rfs
            ->expects($this->once())
            ->method('isTlsDisabled')
            ->willReturn(true);

        // Mock Config
        $config = $this
            ->getMockBuilder('Composer\Config')
            ->getMock();

        // Mock Composer
        $composer = $this
            ->getMockBuilder('Composer\Composer')
            ->setMethods(['getConfig'])
            ->getMock();

        $composer
            ->method('getConfig')
            ->willReturn($config);

        // Mock IOInterface
        $io = $this
            ->getMockBuilder('Composer\IO\IOInterface')
            ->getMock();

        // Mock an Event
        $event = $this
            ->getMockBuilder('Composer\Plugin\PreFileDownloadEvent')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getProcessedUrl',
                    'getRemoteFilesystem',
                    'setRemoteFilesystem'
                ]
            )
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn(self::REPO_URL);

        $event
            ->expects($this->once())
            ->method('getRemoteFilesystem')
            ->willReturn($rfs);

        $event
            ->expects($this->once())
            ->method('setRemoteFilesystem')
            ->with(
                $this->callback(
                    function ($rfs) use ($key) {
                        return true;
                    }
                )
            );

        // Call addKey
        $plugin = new ACFProInstallerPlugin();
        $plugin->activate($composer, $io);
        $plugin->addKey($event);
    }

    public function testPreferKeyFromEnv()
    {
        // The key that should be available in the .env file
        $fileKey = 'DOT_ENV_KEY';
        $key = 'ENV_KEY';

        // Make key available in the .env file
        file_put_contents(
            getcwd() . DIRECTORY_SEPARATOR . '.env',
            EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME . '=' . $fileKey
        );

        // Make key available in the ENVIRONMENT
        putenv(EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME . '=' . $key);

        // Mock a RemoteFilesystem
        $rfs = $this
            ->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->disableOriginalConstructor()
            ->setMethods(['getOptions', 'isTlsDisabled'])
            ->getMock();

        $rfs
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);

        $rfs
            ->expects($this->once())
            ->method('isTlsDisabled')
            ->willReturn(true);

        // Mock Config
        $config = $this
            ->getMockBuilder('Composer\Config')
            ->getMock();

        // Mock Composer
        $composer = $this
            ->getMockBuilder('Composer\Composer')
            ->setMethods(['getConfig'])
            ->getMock();

        $composer
            ->method('getConfig')
            ->willReturn($config);

        // Mock IOInterface
        $io = $this
            ->getMockBuilder('Composer\IO\IOInterface')
            ->getMock();

        // Mock an Event
        $event = $this
            ->getMockBuilder('Composer\Plugin\PreFileDownloadEvent')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getProcessedUrl',
                    'getRemoteFilesystem',
                    'setRemoteFilesystem'
                ]
            )
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn(self::REPO_URL);

        $event
            ->expects($this->once())
            ->method('getRemoteFilesystem')
            ->willReturn($rfs);

        $event
            ->expects($this->once())
            ->method('setRemoteFilesystem')
            ->with(
                $this->callback(
                    function ($rfs) use ($key) {
                        return true;
                    }
                )
            );

        // Call addKey
        $plugin = new ACFProInstallerPlugin();
        $plugin->activate($composer, $io);
        $plugin->addKey($event);
    }

    public function testThrowExceptionWhenKeyIsMissing()
    {
        // Expect an Exception
        $this->expectException(
            MissingKeyException::class,
            EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME
        );

        // Mock Composer
        $composer = $this
            ->getMockBuilder('Composer\Composer')
            ->setMethods(['getConfig'])
            ->getMock();

        $composer
            ->method('getConfig')
            ->willReturn(new Config());

        // Mock IOInterface
        $io = $this
            ->getMockBuilder('Composer\IO\IOInterface')
            ->getMock();

        // Mock a RemoteFilesystem
        $rfs = $this
            ->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->disableOriginalConstructor()
            ->getMock();

        // Mock an Event
        $event = $this
            ->getMockBuilder('Composer\Plugin\PreFileDownloadEvent')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getProcessedUrl',
                    'getRemoteFilesystem'
                ]
            )
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn(self::REPO_URL);

        $event
            ->expects($this->once())
            ->method('getRemoteFilesystem')
            ->willReturn($rfs);

        // Call addKey
        $plugin = new ACFProInstallerPlugin();
        $plugin->activate($composer, $io);
        $plugin->addKey($event);
    }

    public function testOnlyAddKeyOnAcfUrl()
    {
        // Make key available in the ENVIRONMENT
        putenv(EnvironmentVariableLicenseKeyProvider::ENV_VARIABLE_NAME . '=KEY');

        // Mock an Event
        $event = $this
            ->getMockBuilder('Composer\Plugin\PreFileDownloadEvent')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getProcessedUrl',
                    'getRemoteFilesystem',
                    'setRemoteFilesystem'
                ]
            )
            ->getMock();

        $event
            ->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn('another-url');

        $event
            ->expects($this->never())
            ->method('getRemoteFilesystem');

        $event
            ->expects($this->never())
            ->method('setRemoteFilesystem');

        // Call addKey
        $plugin = new ACFProInstallerPlugin();
        $plugin->addKey($event);
    }
}
