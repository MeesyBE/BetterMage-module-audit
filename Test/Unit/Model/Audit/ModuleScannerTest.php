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
            ->method('getAll')
            ->willReturn([]);

        $result = $this->scanner->scan();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testScanReturnsModuleDataInterfaces(): void
    {
        $this->moduleList
            ->method('getAll')
            ->willReturn([
                'Magento_Catalog' => ['name' => 'Magento_Catalog', 'setup_version' => '1.0.0', 'path' => ''],
                'Magento_Sales' => ['name' => 'Magento_Sales', 'setup_version' => '2.0.0', 'path' => ''],
            ]);

        // No module path means no feature file checks
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->scanner->scan();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(ModuleDataInterface::class, $result);
    }

    public function testScanDetectsModuleFeatures(): void
    {
        $this->moduleList
            ->method('getAll')
            ->willReturn([
                'Magento_Catalog' => [
                    'name' => 'Magento_Catalog',
                    'setup_version' => '1.0.0',
                    'path' => '/app/code/Magento/Catalog',
                ],
            ]);

        // All feature files exist
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('<plugin>test</plugin>');

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
            ->method('getAll')
            ->willReturn([
                'Unknown_Module' => [],
            ]);

        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->scanner->scan();

        $this->assertCount(1, $result);

        $module = $result[0];
        $this->assertEquals('Unknown_Module', $module->getName());
        $this->assertEquals('0.0.0', $module->getVersion());
    }
}