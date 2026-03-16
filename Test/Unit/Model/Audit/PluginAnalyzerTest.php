<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Audit\PluginAnalyzer;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Interception\ConfigInterface as InterceptionConfig;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PluginAnalyzer.
 *
 * Tests plugin detection, chain depth calculation, and scoring.
 */
class PluginAnalyzerTest extends TestCase
{
    private PluginAnalyzer $analyzer;
    private InterceptionConfig&MockObject $interceptionConfig;
    private ModuleDirReader&MockObject $moduleDirReader;
    private FileDriver&MockObject $fileDriver;

    protected function setUp(): void
    {
        $this->interceptionConfig = $this->createMock(InterceptionConfig::class);
        $this->moduleDirReader = $this->createMock(ModuleDirReader::class);
        $this->fileDriver = $this->createMock(FileDriver::class);

        $this->analyzer = new PluginAnalyzer(
            $this->interceptionConfig,
            $this->moduleDirReader,
            $this->fileDriver,
        );
    }

    public function testAnalyzeReturnsEmptyArrayWhenNoModules(): void
    {
        $this->moduleDirReader->method('getModuleConfigDir')->willReturn([]);

        $result = $this->analyzer->analyze();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAnalyzeReturnsPluginDataFromDiXml(): void
    {
        $diXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="Magento\\Catalog\\Model\\Product">
        <plugin name="test_plugin" type="Vendor\\Module\\Plugin\\ProductPlugin" sortOrder="10" />
    </type>
</config>
XML;
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => $path === '/app/code/Vendor/Module/etc/di.xml');

        $this->fileDriver->method('fileGetContents')
            ->willReturn($diXml);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PluginDataInterface::class, $result[0]);
        $this->assertSame('Magento\\Catalog\\Model\\Product', $result[0]->getInterceptedClass());
        $this->assertSame('Vendor\\Module\\Plugin\\ProductPlugin', $result[0]->getPluginClass());
        $this->assertSame(10, $result[0]->getSortOrder());
        $this->assertFalse($result[0]->isDisabled());
    }

    public function testAnalyzeDetectsDisabledPlugins(): void
    {
        $diXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="Magento\\Catalog\\Model\\Product">
        <plugin name="disabled_plugin" type="Vendor\\Module\\Plugin\\Disabled" disabled="true" />
    </type>
</config>
XML;
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => $path === '/app/code/Vendor/Module/etc/di.xml');

        $this->fileDriver->method('fileGetContents')
            ->willReturn($diXml);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->isDisabled());
        $this->assertSame(0, $result[0]->getScore());
    }

    public function testChainDepthCalculation(): void
    {
        $diXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="Magento\\Catalog\\Model\\Product">
        <plugin name="plugin_a" type="Vendor\\A\\Plugin\\ProductPlugin" sortOrder="10" />
        <plugin name="plugin_b" type="Vendor\\B\\Plugin\\ProductPlugin" sortOrder="20" />
        <plugin name="plugin_c" type="Vendor\\C\\Plugin\\ProductPlugin" sortOrder="30" />
    </type>
</config>
XML;
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => $path === '/app/code/Vendor/Module/etc/di.xml');

        $this->fileDriver->method('fileGetContents')
            ->willReturn($diXml);

        $result = $this->analyzer->analyze();

        $this->assertCount(3, $result);
        foreach ($result as $plugin) {
            $this->assertSame(3, $plugin->getChainDepth());
        }
    }

    public function testModuleNameExtraction(): void
    {
        $diXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="SomeClass">
        <plugin name="test" type="Vendor\\Module\\Plugin\\SomePlugin" />
    </type>
</config>
XML;
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => $path === '/app/code/Vendor/Module/etc/di.xml');

        $this->fileDriver->method('fileGetContents')
            ->willReturn($diXml);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertSame('Vendor_Module', $result[0]->getModuleName());
    }

    public function testCoreClassInterceptionIncreasesScore(): void
    {
        $diXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="Magento\\Catalog\\Model\\Product">
        <plugin name="core_plugin" type="Vendor\\Module\\Plugin\\ProductPlugin" />
    </type>
    <type name="Vendor\\Custom\\Model\\Something">
        <plugin name="custom_plugin" type="Vendor\\Module\\Plugin\\SomethingPlugin" />
    </type>
</config>
XML;
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => $path === '/app/code/Vendor/Module/etc/di.xml');

        $this->fileDriver->method('fileGetContents')
            ->willReturn($diXml);

        $result = $this->analyzer->analyze();

        $this->assertCount(2, $result);
        // Core class plugin should have higher score
        $coreScore = $result[0]->getScore();
        $customScore = $result[1]->getScore();
        $this->assertGreaterThan($customScore, $coreScore);
    }

    public function testSkipsInvalidDiXml(): void
    {
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => $path === '/app/code/Vendor/Module/etc/di.xml');

        $this->fileDriver->method('fileGetContents')
            ->willReturn('not valid xml <<<<');

        $result = $this->analyzer->analyze();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testScansFrontendAndAdminhtmlScopes(): void
    {
        $globalDiXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="GlobalClass">
        <plugin name="global_plugin" type="Vendor\\Module\\Plugin\\GlobalPlugin" />
    </type>
</config>
XML;
        $frontendDiXml = <<<XML
<?xml version="1.0"?>
<config>
    <type name="FrontendClass">
        <plugin name="frontend_plugin" type="Vendor\\Module\\Plugin\\FrontendPlugin" />
    </type>
</config>
XML;
        $this->moduleDirReader->method('getModuleConfigDir')
            ->willReturn(['Vendor_Module' => '/app/code/Vendor/Module/etc']);

        $this->fileDriver->method('isExists')
            ->willReturnCallback(fn(string $path) => in_array($path, [
                '/app/code/Vendor/Module/etc/di.xml',
                '/app/code/Vendor/Module/etc/frontend/di.xml',
            ]));

        $this->fileDriver->method('fileGetContents')
            ->willReturnCallback(fn(string $path) => match ($path) {
                '/app/code/Vendor/Module/etc/di.xml' => $globalDiXml,
                '/app/code/Vendor/Module/etc/frontend/di.xml' => $frontendDiXml,
                default => '',
            });

        $result = $this->analyzer->analyze();

        $this->assertCount(2, $result);
    }
}
