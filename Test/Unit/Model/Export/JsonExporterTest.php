<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Export\JsonExporter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JsonExporter.
 */
class JsonExporterTest extends TestCase
{
    private JsonExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new JsonExporter();
    }

    public function testGetMimeType(): void
    {
        $this->assertEquals('application/json', $this->exporter->getMimeType());
    }

    public function testGetFileExtension(): void
    {
        $this->assertEquals('json', $this->exporter->getFileExtension());
    }

    public function testExportReturnsValidJson(): void
    {
        $report = $this->createMockReport();
        
        $json = $this->exporter->export($report);
        
        $this->assertIsString($json);
        $this->assertNotEmpty($json);
        
        // Verify it's valid JSON
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNull(json_last_error_msg() === 'No error' ? null : json_last_error_msg());
    }

    public function testExportContainsMetadata(): void
    {
        $report = $this->createMockReport();
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('version', $data['metadata']);
        $this->assertArrayHasKey('timestamp', $data['metadata']);
        $this->assertEquals('1.0', $data['metadata']['version']);
    }

    public function testExportContainsSummary(): void
    {
        $report = $this->createMockReport();
        $report->method('getScore')->willReturn(87);
        $report->method('getGrade')->willReturn('B');
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('summary', $data);
        $this->assertEquals(87, $data['summary']['score']);
        $this->assertEquals('B', $data['summary']['grade']);
    }

    public function testExportContainsStatistics(): void
    {
        $report = $this->createMockReport();
        $report->method('getStatistics')->willReturn([
            'total_modules' => 150,
            'total_observers' => 200,
        ]);
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('statistics', $data);
        $this->assertEquals(150, $data['statistics']['total_modules']);
        $this->assertEquals(200, $data['statistics']['total_observers']);
    }

    public function testExportContainsModules(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('getName')->willReturn('Magento_Catalog');
        $module->method('getVersion')->willReturn('1.0.0');
        $module->method('isEnabled')->willReturn(true);
        $module->method('getScore')->willReturn(3);
        $module->method('getScoreReason')->willReturn('No issues');
        $module->method('hasRoutes')->willReturn(true);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('hasConfig')->willReturn(true);
        $module->method('hasDatabase')->willReturn(true);
        $module->method('getDependents')->willReturn([]);
        $module->method('getRecommendation')->willReturn('Module OK');
        
        $report = $this->createMockReport();
        $report->method('getModules')->willReturn([$module]);
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('modules', $data);
        $this->assertCount(1, $data['modules']);
        $this->assertEquals('Magento_Catalog', $data['modules'][0]['name']);
        $this->assertEquals('1.0.0', $data['modules'][0]['version']);
        $this->assertTrue($data['modules'][0]['features']['has_routes']);
    }

    public function testExportContainsObservers(): void
    {
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('getModuleName')->willReturn('Magento_Catalog');
        $observer->method('getEventName')->willReturn('catalog_product_save_after');
        $observer->method('getObserverClass')->willReturn('Magento\Catalog\Observer\ProductObserver');
        $observer->method('getObserverMethod')->willReturn('execute');
        $observer->method('isValid')->willReturn(true);
        $observer->method('isHighFrequency')->willReturn(false);
        $observer->method('getScore')->willReturn(2);
        $observer->method('getScope')->willReturn('global');
        $observer->method('isAsync')->willReturn(false);
        
        $report = $this->createMockReport();
        $report->method('getObservers')->willReturn([$observer]);
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('observers', $data);
        $this->assertCount(1, $data['observers']);
        $this->assertEquals('Magento_Catalog', $data['observers'][0]['module_name']);
        $this->assertEquals('catalog_product_save_after', $data['observers'][0]['event_name']);
    }

    public function testExportContainsPlugins(): void
    {
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getModuleName')->willReturn('Vendor_Module');
        $plugin->method('getInterceptedClass')->willReturn('Magento\Catalog\Model\Product');
        $plugin->method('getInterceptedMethod')->willReturn('save');
        $plugin->method('getPluginClass')->willReturn('Vendor\Module\Plugin\ProductPlugin');
        $plugin->method('getPluginType')->willReturn('around');
        $plugin->method('getSortOrder')->willReturn(10);
        $plugin->method('isDisabled')->willReturn(false);
        $plugin->method('getChainDepth')->willReturn(2);
        $plugin->method('getScore')->willReturn(5);
        $plugin->method('likelyHasBusinessLogic')->willReturn(true);
        
        $report = $this->createMockReport();
        $report->method('getPlugins')->willReturn([$plugin]);
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('plugins', $data);
        $this->assertCount(1, $data['plugins']);
        $this->assertEquals('Vendor_Module', $data['plugins'][0]['module_name']);
        $this->assertEquals('around', $data['plugins'][0]['plugin_type']);
    }

    public function testExportContainsTopIssues(): void
    {
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('getModuleName')->willReturn('Test_Module');
        $observer->method('getEventName')->willReturn('controller_action_predispatch');
        $observer->method('getObserverClass')->willReturn('Test\Observer');
        $observer->method('isHighFrequency')->willReturn(true);
        $observer->method('getScore')->willReturn(8);
        
        $report = $this->createMockReport();
        $report->method('getObservers')->willReturn([$observer]);
        
        $json = $this->exporter->export($report);
        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('top_issues', $data);
        $this->assertNotEmpty($data['top_issues']);
        $this->assertEquals('observer', $data['top_issues'][0]['type']);
        $this->assertEquals('high', $data['top_issues'][0]['severity']);
    }

    public function testExportPrettyPrintsJson(): void
    {
        $report = $this->createMockReport();
        
        $json = $this->exporter->export($report);
        
        // Pretty-printed JSON should contain newlines
        $this->assertStringContainsString("\n", $json);
    }

    /**
     * Create mock audit report.
     */
    private function createMockReport(): AuditReportInterface
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getExecutedAt')->willReturn('2026-02-28T10:00:00+00:00');
        $report->method('getScore')->willReturn(85);
        $report->method('getGrade')->willReturn('B');
        $report->method('getStatistics')->willReturn([]);
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([]);
        
        return $report;
    }
}
