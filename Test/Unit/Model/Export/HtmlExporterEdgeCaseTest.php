<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Export\HtmlExporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for HtmlExporter.
 */
class HtmlExporterEdgeCaseTest extends TestCase
{
    private HtmlExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new HtmlExporter();
    }

    public function testExportWithEmptyReport(): void
    {
        $report = $this->createReportMock(0, 'A', [], [], [], []);

        $html = $this->exporter->export($report);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('Grade: A', $html);
    }

    public function testExportWithZeroScore(): void
    {
        $report = $this->createReportMock(0, 'F', [], [], [], []);

        $html = $this->exporter->export($report);

        self::assertStringContainsString('Grade: F', $html);
        self::assertStringContainsString('>0<', $html);
    }

    public function testExportWithSpecialCharactersInModuleName(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('getName')->willReturn('Vendor_Module<script>alert("xss")</script>');
        $module->method('getScore')->willReturn(5);
        $module->method('getRecommendation')->willReturn('Test & review');
        $module->method('isEnabled')->willReturn(true);
        $module->method('hasRoutes')->willReturn(false);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('hasDatabase')->willReturn(false);
        $module->method('hasConfig')->willReturn(false);
        $module->method('getScoreReason')->willReturn('Unused');

        $report = $this->createReportMock(50, 'E', [$module], [], [], []);

        $html = $this->exporter->export($report);

        self::assertIsString($html);
        self::assertStringContainsString('Modules', $html);
    }

    public function testExportWithManyModules(): void
    {
        $modules = [];
        for ($i = 0; $i < 250; $i++) {
            $module = $this->createMock(ModuleDataInterface::class);
            $module->method('getName')->willReturn("Vendor_Module{$i}");
            $module->method('getScore')->willReturn($i % 10);
            $module->method('getRecommendation')->willReturn('OK');
            $module->method('isEnabled')->willReturn(true);
            $module->method('hasRoutes')->willReturn($i % 2 === 0);
            $module->method('hasObservers')->willReturn($i % 3 === 0);
            $module->method('hasPlugins')->willReturn($i % 4 === 0);
            $module->method('hasCron')->willReturn(false);
            $module->method('hasDatabase')->willReturn(false);
            $module->method('hasConfig')->willReturn(false);
            $module->method('getScoreReason')->willReturn('');
            $modules[] = $module;
        }

        $report = $this->createReportMock(30, 'F', $modules, [], [], $this->createDefaultStats());

        $html = $this->exporter->export($report);

        self::assertStringContainsString('Vendor_Module0', $html);
        self::assertStringContainsString('Vendor_Module249', $html);
    }

    public function testExportContainsAllSections(): void
    {
        $report = $this->createReportMock(85, 'B', [], [], [], []);

        $html = $this->exporter->export($report);

        self::assertStringContainsString('Score', $html);
        self::assertStringContainsString('Statistics', $html);
    }

    public function testExportWithPerfectScore(): void
    {
        $report = $this->createReportMock(100, 'A', [], [], [], []);

        $html = $this->exporter->export($report);

        self::assertStringContainsString('Grade: A', $html);
        self::assertStringContainsString('>100<', $html);
    }

    public function testGetMimeType(): void
    {
        self::assertSame('text/html', $this->exporter->getMimeType());
    }

    public function testGetFileExtension(): void
    {
        self::assertSame('html', $this->exporter->getFileExtension());
    }

    private function createReportMock(
        int $score,
        string $grade,
        array $modules,
        array $observers,
        array $plugins,
        array $stats = []
    ): AuditReportInterface|MockObject {
        $defaultStats = $this->createDefaultStats();
        $stats = array_merge($defaultStats, $stats);

        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getScore')->willReturn($score);
        $report->method('getGrade')->willReturn($grade);
        $report->method('getModules')->willReturn($modules);
        $report->method('getObservers')->willReturn($observers);
        $report->method('getPlugins')->willReturn($plugins);
        $report->method('getStatistics')->willReturn($stats);
        $report->method('getExecutedAt')->willReturn('2026-02-28 12:00:00');
        $report->method('getTopIssues')->willReturn([]);

        return $report;
    }

    private function createDefaultStats(): array
    {
        return [
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
            'deep_chain_plugins' => 0,
        ];
    }
}
