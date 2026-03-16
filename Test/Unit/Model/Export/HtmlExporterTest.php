<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Export\HtmlExporter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HtmlExporter.
 */
class HtmlExporterTest extends TestCase
{
    private HtmlExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new HtmlExporter();
    }

    public function testGetMimeType(): void
    {
        $this->assertEquals('text/html', $this->exporter->getMimeType());
    }

    public function testGetFileExtension(): void
    {
        $this->assertEquals('html', $this->exporter->getFileExtension());
    }

    public function testExportReturnsValidHtml(): void
    {
        $report = $this->createMockReport();
        
        $html = $this->exporter->export($report);
        
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        
        // Verify basic HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('<body>', $html);
    }

    public function testExportContainsTitle(): void
    {
        $report = $this->createMockReport();
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('<title>BetterMagento Module Audit Report</title>', $html);
        $this->assertStringContainsString('<h1>BetterMagento Module Audit Report</h1>', $html);
    }

    public function testExportContainsTimestamp(): void
    {
        $report = $this->createMockReport();
        $report->method('getExecutedAt')->willReturn('2026-02-28T10:00:00+00:00');
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('2026-02-28T10:00:00+00:00', $html);
    }

    public function testExportContainsScore(): void
    {
        $report = $this->createMockReport();
        $report->method('getScore')->willReturn(87);
        $report->method('getGrade')->willReturn('B');
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('87', $html);
        $this->assertStringContainsString('Grade: B', $html);
    }

    public function testExportContainsStatistics(): void
    {
        $report = $this->createMockReport();
        $report->method('getStatistics')->willReturn([
            'total_modules' => 150,
            'total_observers' => 200,
            'total_plugins' => 100,
            'enabled_modules' => 150,
            'modules_with_routes' => 50,
            'modules_with_observers' => 40,
            'modules_with_plugins' => 60,
            'modules_with_cron' => 20,
            'high_frequency_observers' => 15,
            'invalid_observers' => 2,
            'around_plugins' => 25,
            'deep_chains' => 5,
        ]);
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('150', $html);
        $this->assertStringContainsString('200', $html);
        $this->assertStringContainsString('Statistics Overview', $html);
    }

    public function testExportContainsEmbeddedCss(): void
    {
        $report = $this->createMockReport();
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('</style>', $html);
        $this->assertStringContainsString('font-family:', $html);
    }

    public function testExportContainsModulesSection(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('getName')->willReturn('Magento_Catalog');
        $module->method('getVersion')->willReturn('1.0.0');
        $module->method('getScore')->willReturn(2);
        $module->method('hasRoutes')->willReturn(true);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('getRecommendation')->willReturn('Module OK');
        
        $report = $this->createMockReport();
        $report->method('getModules')->willReturn([$module]);
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('Module Details', $html);
        $this->assertStringContainsString('Magento_Catalog', $html);
    }

    public function testExportContainsObserversSection(): void
    {
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('getModuleName')->willReturn('Magento_Catalog');
        $observer->method('getEventName')->willReturn('catalog_product_save_after');
        $observer->method('getObserverClass')->willReturn('Magento\Catalog\Observer\ProductObserver');
        $observer->method('getScore')->willReturn(3);
        $observer->method('isHighFrequency')->willReturn(false);
        $observer->method('isValid')->willReturn(true);
        
        $report = $this->createMockReport();
        $report->method('getObservers')->willReturn([$observer]);
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('Observer Details', $html);
        $this->assertStringContainsString('catalog_product_save_after', $html);
    }

    public function testExportContainsPluginsSection(): void
    {
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getModuleName')->willReturn('Vendor_Module');
        $plugin->method('getInterceptedClass')->willReturn('Magento\Catalog\Model\Product');
        $plugin->method('getPluginClass')->willReturn('Vendor\Module\Plugin\ProductPlugin');
        $plugin->method('getPluginType')->willReturn('around');
        $plugin->method('getChainDepth')->willReturn(2);
        $plugin->method('getScore')->willReturn(5);
        
        $report = $this->createMockReport();
        $report->method('getPlugins')->willReturn([$plugin]);
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('Plugin Details', $html);
        $this->assertStringContainsString('Magento\Catalog\Model\Product', $html);
    }

    public function testExportContainsTopIssuesWhenPresent(): void
    {
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('getModuleName')->willReturn('Test_Module');
        $observer->method('getEventName')->willReturn('controller_action_predispatch');
        $observer->method('getObserverClass')->willReturn('Test\Observer');
        $observer->method('isHighFrequency')->willReturn(true);
        $observer->method('getScore')->willReturn(8);
        
        $report = $this->createMockReport();
        $report->method('getObservers')->willReturn([$observer]);
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('Top Issues', $html);
        $this->assertStringContainsString('controller_action_predispatch', $html);
    }

    public function testExportShowsNoIssuesMessageWhenNoIssues(): void
    {
        $report = $this->createMockReport();
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('No major performance issues detected', $html);
    }

    public function testExportContainsFooter(): void
    {
        $report = $this->createMockReport();
        
        $html = $this->exporter->export($report);
        
        $this->assertStringContainsString('<footer>', $html);
        $this->assertStringContainsString('BetterMagento Module Audit', $html);
    }

    public function testExportIsResponsive(): void
    {
        $report = $this->createMockReport();
        
        $html = $this->exporter->export($report);
        
        // Check for viewport meta tag
        $this->assertStringContainsString('viewport', $html);
        $this->assertStringContainsString('width=device-width', $html);
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
        $report->method('getStatistics')->willReturn([
            'total_modules' => 0,
            'enabled_modules' => 0,
            'modules_with_routes' => 0,
            'modules_with_observers' => 0,
            'modules_with_plugins' => 0,
            'modules_with_cron' => 0,
            'total_observers' => 0,
            'high_frequency_observers' => 0,
            'invalid_observers' => 0,
            'total_plugins' => 0,
            'around_plugins' => 0,
            'deep_chains' => 0,
        ]);
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([]);
        
        return $report;
    }
}
