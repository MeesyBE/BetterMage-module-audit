<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Audit\ModuleScanner;
use BetterMagento\ModuleAudit\Model\Audit\ObserverAnalyzer;
use BetterMagento\ModuleAudit\Model\Audit\PluginAnalyzer;
use BetterMagento\ModuleAudit\Model\Audit\Runner;
use BetterMagento\ModuleAudit\Model\Audit\ScoreCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RunnerTest extends TestCase
{
    private Runner $runner;
    private ModuleScanner&MockObject $moduleScannerMock;
    private ObserverAnalyzer&MockObject $observerAnalyzerMock;
    private PluginAnalyzer&MockObject $pluginAnalyzerMock;
    private ScoreCalculator&MockObject $scoreCalculatorMock;

    protected function setUp(): void
    {
        $this->moduleScannerMock = $this->createMock(ModuleScanner::class);
        $this->observerAnalyzerMock = $this->createMock(ObserverAnalyzer::class);
        $this->pluginAnalyzerMock = $this->createMock(PluginAnalyzer::class);
        $this->scoreCalculatorMock = $this->createMock(ScoreCalculator::class);

        $this->runner = new Runner(
            $this->moduleScannerMock,
            $this->observerAnalyzerMock,
            $this->pluginAnalyzerMock,
            $this->scoreCalculatorMock,
        );
    }

    public function testExecuteReturnsAuditReport(): void
    {
        $this->moduleScannerMock->method('scan')->willReturn([]);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $report = $this->runner->execute();

        $this->assertNotEmpty($report->getExecutedAt());
        $this->assertSame([], $report->getModules());
        $this->assertSame([], $report->getObservers());
        $this->assertSame([], $report->getPlugins());
    }

    public function testExecuteCallsAllPhases(): void
    {
        $this->moduleScannerMock->expects($this->once())->method('scan')->willReturn([]);
        $this->observerAnalyzerMock->expects($this->once())->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->expects($this->once())->method('analyze')->willReturn([]);
        $this->scoreCalculatorMock->expects($this->once())->method('calculateModuleScores');
        $this->scoreCalculatorMock->expects($this->once())->method('calculateOverallScore');

        $this->runner->execute();
    }

    public function testExecuteSetsModulesOnReport(): void
    {
        $module = $this->createModuleMock('Vendor_ModuleA', true);
        $this->moduleScannerMock->method('scan')->willReturn([$module]);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $report = $this->runner->execute();

        $this->assertCount(1, $report->getModules());
    }

    public function testStatisticsTotalModules(): void
    {
        $modules = [
            $this->createModuleMock('Vendor_A', true),
            $this->createModuleMock('Vendor_B', false),
            $this->createModuleMock('Vendor_C', true),
        ];
        $this->moduleScannerMock->method('scan')->willReturn($modules);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $report = $this->runner->execute();
        $stats = $report->getStatistics();

        $this->assertSame(3, $stats['total_modules']);
        $this->assertSame(2, $stats['enabled_modules']);
    }

    public function testStatisticsObserverCounts(): void
    {
        $observers = [
            $this->createObserverMock(true, true),
            $this->createObserverMock(false, false),
            $this->createObserverMock(true, false),
        ];
        $this->moduleScannerMock->method('scan')->willReturn([]);
        $this->observerAnalyzerMock->method('analyze')->willReturn($observers);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $report = $this->runner->execute();
        $stats = $report->getStatistics();

        $this->assertSame(3, $stats['total_observers']);
        $this->assertSame(2, $stats['high_frequency_observers']);
        $this->assertSame(1, $stats['invalid_observers']);
    }

    public function testStatisticsPluginCounts(): void
    {
        $plugins = [
            $this->createPluginMock('around', 5),
            $this->createPluginMock('before', 1),
            $this->createPluginMock('around', 4),
            $this->createPluginMock('after', 2),
        ];
        $this->moduleScannerMock->method('scan')->willReturn([]);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn($plugins);

        $report = $this->runner->execute();
        $stats = $report->getStatistics();

        $this->assertSame(4, $stats['total_plugins']);
        $this->assertSame(2, $stats['around_plugins']);
        $this->assertSame(2, $stats['deep_chains']); // chainDepth >= 4
    }

    public function testStatisticsModuleFeatureFlags(): void
    {
        $moduleWithRoutes = $this->createModuleMock('Vendor_A', true, routes: true);
        $moduleWithObservers = $this->createModuleMock('Vendor_B', true, observers: true);
        $moduleWithPlugins = $this->createModuleMock('Vendor_C', true, plugins: true);
        $moduleWithCron = $this->createModuleMock('Vendor_D', true, cron: true);

        $this->moduleScannerMock->method('scan')
            ->willReturn([$moduleWithRoutes, $moduleWithObservers, $moduleWithPlugins, $moduleWithCron]);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $report = $this->runner->execute();
        $stats = $report->getStatistics();

        $this->assertSame(1, $stats['modules_with_routes']);
        $this->assertSame(1, $stats['modules_with_observers']);
        $this->assertSame(1, $stats['modules_with_plugins']);
        $this->assertSame(1, $stats['modules_with_cron']);
    }

    public function testExecutedAtIsIso8601(): void
    {
        $this->moduleScannerMock->method('scan')->willReturn([]);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $report = $this->runner->execute();

        // Verify it parses as a valid date
        $timestamp = strtotime($report->getExecutedAt());
        $this->assertNotFalse($timestamp);
    }

    public function testScoreCalculatorReceivesReportData(): void
    {
        $modules = [$this->createModuleMock('Vendor_A', true)];
        $this->moduleScannerMock->method('scan')->willReturn($modules);
        $this->observerAnalyzerMock->method('analyze')->willReturn([]);
        $this->pluginAnalyzerMock->method('analyze')->willReturn([]);

        $this->scoreCalculatorMock->expects($this->once())
            ->method('calculateModuleScores')
            ->with($modules);

        $this->scoreCalculatorMock->expects($this->once())
            ->method('calculateOverallScore')
            ->with($this->callback(function ($report) {
                return count($report->getModules()) === 1;
            }));

        $this->runner->execute();
    }

    private function createModuleMock(
        string $name,
        bool $enabled,
        bool $routes = false,
        bool $observers = false,
        bool $plugins = false,
        bool $cron = false,
    ): ModuleDataInterface&MockObject {
        $mock = $this->createMock(ModuleDataInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isEnabled')->willReturn($enabled);
        $mock->method('hasRoutes')->willReturn($routes);
        $mock->method('hasObservers')->willReturn($observers);
        $mock->method('hasPlugins')->willReturn($plugins);
        $mock->method('hasCron')->willReturn($cron);
        return $mock;
    }

    private function createObserverMock(bool $highFrequency, bool $valid): ObserverDataInterface&MockObject
    {
        $mock = $this->createMock(ObserverDataInterface::class);
        $mock->method('isHighFrequency')->willReturn($highFrequency);
        $mock->method('isValid')->willReturn($valid);
        return $mock;
    }

    private function createPluginMock(string $type, int $chainDepth): PluginDataInterface&MockObject
    {
        $mock = $this->createMock(PluginDataInterface::class);
        $mock->method('getPluginType')->willReturn($type);
        $mock->method('getChainDepth')->willReturn($chainDepth);
        return $mock;
    }
}
