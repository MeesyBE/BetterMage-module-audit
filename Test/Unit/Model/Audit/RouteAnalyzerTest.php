<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Model\Audit\RouteAnalyzer;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Audit\RouteAnalyzer
 */
class RouteAnalyzerTest extends TestCase
{
    private RouteAnalyzer $analyzer;
    private ModuleListInterface|MockObject $moduleList;
    private ModuleDirReader|MockObject $moduleDirReader;
    private File|MockObject $fileDriver;

    protected function setUp(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->moduleDirReader = $this->createMock(ModuleDirReader::class);
        $this->fileDriver = $this->createMock(File::class);

        $this->analyzer = new RouteAnalyzer(
            $this->moduleList,
            $this->moduleDirReader,
            $this->fileDriver
        );
    }

    public function testEmptyModuleListReturnsEmptyResult(): void
    {
        $this->moduleList->method('getAll')->willReturn([]);

        $result = $this->analyzer->analyze();

        self::assertSame([], $result['routes']);
        self::assertSame([], $result['duplicates']);
        self::assertSame([], $result['orphaned']);
        self::assertSame(0, $result['stats']['total']);
    }

    public function testModuleWithFrontendRouteIsParsed(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Module' => ['path' => '/app/code/Vendor/Module'],
        ]);

        $routesXml = <<<XML
<?xml version="1.0"?>
<config>
    <router id="standard">
        <route id="vendor_module" frontName="vendormodule">
            <module name="Vendor_Module"/>
        </route>
    </router>
</config>
XML;

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn(string $path) => str_contains($path, 'frontend/routes.xml')
        );

        $this->fileDriver->method('fileGetContents')->willReturn($routesXml);

        // Module has controllers
        $this->moduleDirReader->method('getModuleDir')->willReturn('/app/code/Vendor/Module');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/app/code/Vendor/Module/Controller/Index.php']);

        $result = $this->analyzer->analyze();

        self::assertCount(1, $result['routes']);
        self::assertSame('Vendor_Module', $result['routes'][0]['module']);
        self::assertSame('frontend', $result['routes'][0]['scope']);
        self::assertSame('vendormodule', $result['routes'][0]['front_name']);
        self::assertTrue($result['routes'][0]['has_controllers']);
        self::assertSame(1, $result['stats']['frontend']);
        self::assertSame(0, $result['stats']['adminhtml']);
    }

    public function testModuleWithNoRouteFileIsSkipped(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_NoRoutes' => ['path' => '/app/code/Vendor/NoRoutes'],
        ]);

        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->analyzer->analyze();

        self::assertSame([], $result['routes']);
        self::assertSame(0, $result['stats']['total']);
    }

    public function testModuleWithNoPathIsSkipped(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Empty' => ['path' => ''],
        ]);

        $result = $this->analyzer->analyze();

        self::assertSame([], $result['routes']);
    }

    public function testOrphanedRoutesAreDetected(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Orphan' => ['path' => '/app/code/Vendor/Orphan'],
        ]);

        $routesXml = <<<XML
<?xml version="1.0"?>
<config>
    <router id="standard">
        <route id="orphan" frontName="orphanroute">
            <module name="Vendor_Orphan"/>
        </route>
    </router>
</config>
XML;

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn(string $path) => str_contains($path, 'frontend/routes.xml')
        );
        $this->fileDriver->method('fileGetContents')->willReturn($routesXml);

        // No controllers exist
        $this->moduleDirReader->method('getModuleDir')->willReturn('/app/code/Vendor/Orphan');
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $result = $this->analyzer->analyze();

        self::assertCount(1, $result['orphaned']);
        self::assertSame('Vendor_Orphan', $result['orphaned'][0]['module']);
    }

    public function testDuplicateFrontNamesAreDetected(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_A' => ['path' => '/app/code/Vendor/A'],
            'Vendor_B' => ['path' => '/app/code/Vendor/B'],
        ]);

        $routesXml = <<<XML
<?xml version="1.0"?>
<config>
    <router id="standard">
        <route id="shared" frontName="samename">
            <module name="Vendor_Shared"/>
        </route>
    </router>
</config>
XML;

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn(string $path) => str_contains($path, 'frontend/routes.xml')
        );
        $this->fileDriver->method('fileGetContents')->willReturn($routesXml);
        $this->moduleDirReader->method('getModuleDir')->willReturn('/tmp/mock');
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $result = $this->analyzer->analyze();

        self::assertArrayHasKey('samename', $result['duplicates']);
        self::assertCount(2, $result['duplicates']['samename']);
    }

    public function testStatsCountByModule(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_Multi' => ['path' => '/app/code/Vendor/Multi'],
        ]);

        $frontendXml = <<<XML
<?xml version="1.0"?>
<config>
    <router id="standard">
        <route id="front1" frontName="front1"><module name="Vendor_Multi"/></route>
    </router>
</config>
XML;

        $adminhtmlXml = <<<XML
<?xml version="1.0"?>
<config>
    <router id="admin">
        <route id="admin1" frontName="admin1"><module name="Vendor_Multi"/></route>
    </router>
</config>
XML;

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturnCallback(
            fn(string $path) => str_contains($path, 'adminhtml') ? $adminhtmlXml : $frontendXml
        );
        $this->moduleDirReader->method('getModuleDir')->willReturn('/tmp/mock');
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $result = $this->analyzer->analyze();

        self::assertSame(2, $result['stats']['total']);
        self::assertSame(1, $result['stats']['frontend']);
        self::assertSame(1, $result['stats']['adminhtml']);
        self::assertSame(2, $result['stats']['by_module']['Vendor_Multi']);
    }

    public function testInvalidXmlReturnsEmptyRoutes(): void
    {
        $this->moduleList->method('getAll')->willReturn([
            'Vendor_BadXml' => ['path' => '/app/code/Vendor/BadXml'],
        ]);

        $this->fileDriver->method('isExists')->willReturnCallback(
            fn(string $path) => str_contains($path, 'frontend/routes.xml')
        );
        $this->fileDriver->method('fileGetContents')->willReturn('<not>valid<xml');

        $result = $this->analyzer->analyze();

        self::assertSame([], $result['routes']);
    }
}
