<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Model\Audit\ModuleScanner;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ModuleScanner.
 */
class ModuleScannerTest extends TestCase
{
    private ModuleScanner $scanner;
    private ModuleListInterface $moduleList;
    private File $fileDriver;

    protected function setUp(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->scanner = new ModuleScanner($this->moduleList, $this->fileDriver);
    }

    public function testScanReturnsEmptyArrayWhenNoModules(): void
    {
        $this->moduleList
            ->expects($this->once())
            ->method('getNames')
            ->willReturn([]);

        $result = $this->scanner->scan();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testScanReturnsModuleDataInterfaces(): void
    {
        $this->moduleList
            ->expects($this->once())
            ->method('getNames')
            ->willReturn(['Magento_Catalog', 'Magento_Sales']);

        $this->moduleList
            ->method('getOne')
            ->willReturnMap([
                ['Magento_Catalog', ['name' => 'Magento_Catalog', 'setup_version' => '1.0.0']],
                ['Magento_Sales', ['name' => 'Magento_Sales', 'setup_version' => '2.0.0']],
            ]);

        // Mock file existence checks
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->scanner->scan();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(ModuleDataInterface::class, $result);
    }

    public function testScanDetectsModuleFeatures(): void
    {
        $this->moduleList
            ->expects($this->once())
            ->method('getNames')
            ->willReturn(['Magento_Catalog']);

        $this->moduleList
            ->method('getOne')
            ->with('Magento_Catalog')
            ->willReturn(['name' => 'Magento_Catalog', 'setup_version' => '1.0.0']);

        // All feature files exist
        $this->fileDriver->method('isExists')->willReturn(true);

        $result = $this->scanner->scan();

        $this->assertCount(1, $result);
        
        $module = $result[0];
        $this->assertEquals('Magento_Catalog', $module->getName());
        $this->assertTrue($module->hasRoutes());
        $this->assertTrue($module->hasObservers());
        $this->assertTrue($module->hasPlugins());
        $this->assertTrue($module->hasCron());
        $this->assertTrue($module->hasConfig());
        $this->assertTrue($module->hasDatabase());
    }

    public function testScanHandlesMissingModuleInfo(): void
    {
        $this->moduleList
            ->expects($this->once())
            ->method('getNames')
            ->willReturn(['Unknown_Module']);

        $this->moduleList
            ->method('getOne')
            ->with('Unknown_Module')
            ->willReturn(null);

        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->scanner->scan();

        $this->assertCount(1, $result);
        
        $module = $result[0];
        $this->assertEquals('Unknown_Module', $module->getName());
        $this->assertEquals('Unknown', $module->getVersion());
    }
}
