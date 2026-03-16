<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Model\Audit\ConfigUsageChecker;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Audit\ConfigUsageChecker
 */
class ConfigUsageCheckerTest extends TestCase
{
    private ConfigUsageChecker $checker;
    private ModuleListInterface|MockObject $moduleList;
    private ModuleDirReader|MockObject $moduleDirReader;
    private File|MockObject $fileDriver;
    private ScopeConfigInterface|MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->moduleDirReader = $this->createMock(ModuleDirReader::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $this->checker = new ConfigUsageChecker(
            $this->moduleList,
            $this->moduleDirReader,
            $this->fileDriver,
            $this->scopeConfig
        );
    }

    public function testEmptyModuleListReturnsEmptyResult(): void
    {
        $this->moduleList->method('getAll')->willReturn([]);

        $result = $this->checker->analyze();

        self::assertSame([], $result['modules']);
        self::assertSame(0, $result['stats']['total_paths']);
    }

    public function testModuleWithNoConfigFilesIsSkipped(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_NoConfig' => ['path' => '/app/code/Vendor/NoConfig'],
        ]);

        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->checker->analyze();

        self::assertSame([], $result['modules']);
        self::assertSame(0, $result['stats']['modules_with_config']);
    }

    public function testModuleWithSystemXmlExtractsPaths(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Test' => ['path' => '/app/code/Vendor/Test'],
        ]);

        $systemXml = <<<XML
<?xml version="1.0"?>
<config>
    <system>
        <section id="vendor_test">
            <group id="general">
                <field id="enabled"/>
                <field id="title"/>
            </group>
        </section>
    </system>
</config>
XML;

        // system.xml exists, config.xml does not
        $this->fileDriver->method('isExists')->willReturnCallback(
            fn(string $path) => str_contains($path, 'system.xml')
        );

        $this->fileDriver->method('fileGetContents')->willReturn($systemXml);

        // No PHP files reference these paths
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $result = $this->checker->analyze();

        self::assertArrayHasKey('Vendor_Test', $result['modules']);
        self::assertContains('vendor_test/general/enabled', $result['modules']['Vendor_Test']['defined_paths']);
        self::assertContains('vendor_test/general/title', $result['modules']['Vendor_Test']['defined_paths']);
    }

    public function testUsedPathsAreDetectedInPhpCode(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Used' => ['path' => '/app/code/Vendor/Used'],
        ]);

        $systemXml = <<<XML
<?xml version="1.0"?>
<config>
    <system>
        <section id="vendor_used">
            <group id="general">
                <field id="enabled"/>
                <field id="orphan"/>
            </group>
        </section>
    </system>
</config>
XML;

        $phpCode = '<?php $this->scopeConfig->getValue("vendor_used/general/enabled");';

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn(string $path) => str_contains($path, 'system.xml') || str_contains($path, '/app/code/Vendor/Used')
        );

        $this->fileDriver->method('fileGetContents')->willReturnCallback(
            fn(string $path) => str_contains($path, 'system.xml') ? $systemXml : $phpCode
        );

        $this->fileDriver->method('isDirectory')->willReturnCallback(
            fn(string $path) => !str_ends_with($path, '.php') && !str_ends_with($path, '.xml')
        );

        $this->fileDriver->method('readDirectory')->willReturn([
            '/app/code/Vendor/Used/Model/Config.php',
        ]);

        $result = $this->checker->analyze();

        $module = $result['modules']['Vendor_Used'];
        self::assertContains('vendor_used/general/enabled', $module['used_paths']);
        self::assertContains('vendor_used/general/orphan', $module['unused_paths']);
    }

    public function testStatsShowCorrectCounts(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_A' => ['path' => '/a'],
            'Vendor_B' => ['path' => '/b'],
        ]);

        // Neither has config files
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->checker->analyze();

        self::assertSame(0, $result['stats']['modules_with_config']);
        self::assertSame(0, $result['stats']['modules_with_unused']);
    }

    public function testModuleWithEmptyPathIsSkipped(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Empty' => ['path' => ''],
        ]);

        $result = $this->checker->analyze();

        self::assertSame([], $result['modules']);
    }
}
