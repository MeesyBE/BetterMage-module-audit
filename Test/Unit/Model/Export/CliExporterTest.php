<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Export\CliExporter;
use PHPUnit\Framework\TestCase;

class CliExporterTest extends TestCase
{
    private CliExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new CliExporter();
    }

    public function testGetMimeType(): void
    {
        $this->assertSame('text/plain', $this->exporter->getMimeType());
    }

    public function testGetFileExtension(): void
    {
        $this->assertSame('txt', $this->exporter->getFileExtension());
    }

    public function testExportContainsHeader(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('BetterMagento Module Audit Report', $output);
        $this->assertStringContainsString('==================================', $output);
    }

    public function testExportContainsScore(): void
    {
        $report = $this->createMockReport(score: 85, grade: 'B');

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('Score: 85/100 (Grade: B)', $output);
    }

    public function testExportContainsModuleStatistics(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('MODULE STATISTICS', $output);
        $this->assertStringContainsString('Total Modules:', $output);
        $this->assertStringContainsString('Enabled Modules:', $output);
    }

    public function testExportContainsObserverStatistics(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('OBSERVER STATISTICS', $output);
        $this->assertStringContainsString('Total Observers:', $output);
    }

    public function testExportContainsPluginStatistics(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('PLUGIN STATISTICS', $output);
        $this->assertStringContainsString('Total Plugins:', $output);
        $this->assertStringContainsString('Around Plugins:', $output);
    }

    public function testExportContainsTopIssuesSection(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('TOP ISSUES', $output);
    }

    public function testExportShowsNoIssuesMessageWhenClean(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('No major performance issues detected', $output);
    }

    public function testExportListsHighImpactObservers(): void
    {
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('isHighFrequency')->willReturn(true);
        $observer->method('getScore')->willReturn(8);
        $observer->method('getObserverClass')->willReturn('Vendor\Module\Observer\HeavyObserver');
        $observer->method('getEventName')->willReturn('checkout_submit_all_after');
        $observer->method('getModuleName')->willReturn('Vendor_Module');

        $report = $this->createMockReport(observers: [$observer]);

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('[OBSERVER]', $output);
        $this->assertStringContainsString('HeavyObserver', $output);
    }

    public function testExportListsHighImpactPlugins(): void
    {
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getScore')->willReturn(9);
        $plugin->method('getPluginClass')->willReturn('Vendor\Module\Plugin\HeavyPlugin');
        $plugin->method('getInterceptedClass')->willReturn('Magento\Catalog\Model\Product');
        $plugin->method('getInterceptedMethod')->willReturn('save');
        $plugin->method('getPluginType')->willReturn('around');

        $report = $this->createMockReport(plugins: [$plugin]);

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('[PLUGIN]', $output);
        $this->assertStringContainsString('HeavyPlugin', $output);
    }

    public function testExportContainsExecutionTimestamp(): void
    {
        $report = $this->createMockReport();

        $output = $this->exporter->export($report);

        $this->assertStringContainsString('Executed:', $output);
        $this->assertStringContainsString('2026-02-28T12:00:00+00:00', $output);
    }

    private function createMockReport(
        int $score = 90,
        string $grade = 'A',
        array $observers = [],
        array $plugins = [],
        array $modules = [],
    ): AuditReportInterface {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getScore')->willReturn($score);
        $report->method('getGrade')->willReturn($grade);
        $report->method('getExecutedAt')->willReturn('2026-02-28T12:00:00+00:00');
        $report->method('getObservers')->willReturn($observers);
        $report->method('getPlugins')->willReturn($plugins);
        $report->method('getModules')->willReturn($modules);
        $report->method('getStatistics')->willReturn([
            'total_modules' => 120,
            'enabled_modules' => 100,
            'modules_with_routes' => 45,
            'modules_with_observers' => 60,
            'modules_with_plugins' => 55,
            'modules_with_cron' => 20,
            'total_observers' => 200,
            'high_frequency_observers' => 15,
            'invalid_observers' => 2,
            'total_plugins' => 180,
            'around_plugins' => 40,
            'deep_chains' => 3,
        ]);

        return $report;
    }
}
