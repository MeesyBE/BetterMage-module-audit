<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Audit\ScoreCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreCalculator.
 *
 * Tests scoring algorithms and grade calculation.
 */
class ScoreCalculatorTest extends TestCase
{
    private ScoreCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ScoreCalculator();
    }

    public function testCalculateOverallScoreStartsAt100(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->expects($this->once())->method('getModules')->willReturn([]);
        $report->expects($this->once())->method('getObservers')->willReturn([]);
        $report->expects($this->once())->method('getPlugins')->willReturn([]);
        
        $report->expects($this->once())->method('setScore')->with(100);
        $report->expects($this->once())->method('setGrade')->with('A');

        $this->calculator->calculateOverallScore($report);
    }

    public function testCalculateGradeA(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([]);
        
        $report->expects($this->once())->method('setGrade')->with('A');

        $this->calculator->calculateOverallScore($report);
    }

    public function testCalculateGradeWithTooManyModules(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        
        // Create 201 mock modules
        $modules = array_fill(0, 201, $this->createMock(ModuleDataInterface::class));
        
        $report->method('getModules')->willReturn($modules);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([]);
        
        // With > 200 modules, penalty is applied
        $report->expects($this->once())
            ->method('setScore')
            ->with($this->lessThan(100));

        $this->calculator->calculateOverallScore($report);
    }

    public function testCalculateModuleScoresForUnusedModule(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('hasRoutes')->willReturn(false);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('isEnabled')->willReturn(true);
        $module->method('hasDatabase')->willReturn(false);
        $module->method('hasConfig')->willReturn(false);

        // Should get penalty for being unused
        $module->expects($this->once())
            ->method('setScore')
            ->with($this->greaterThanOrEqual(3));
        
        $module->expects($this->once())
            ->method('setRecommendation')
            ->with($this->stringContains('Consider disabling'));

        $this->calculator->calculateModuleScores([$module]);
    }

    public function testCalculateModuleScoresForActiveModule(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('hasRoutes')->willReturn(true);
        $module->method('hasObservers')->willReturn(true);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('isEnabled')->willReturn(true);

        // Active module should have low score
        $module->expects($this->once())
            ->method('setScore')
            ->with(0);

        $this->calculator->calculateModuleScores([$module]);
    }

    public function testHighFrequencyObserversPenalty(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('isHighFrequency')->willReturn(true);
        $observer->method('isValid')->willReturn(true);
        
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([$observer, $observer, $observer]); // 3 high-freq
        $report->method('getPlugins')->willReturn([]);
        
        // Should reduce score due to high-frequency observers
        $report->expects($this->once())
            ->method('setScore')
            ->with($this->lessThan(100));

        $this->calculator->calculateOverallScore($report);
    }

    public function testInvalidObserversPenalty(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('isHighFrequency')->willReturn(false);
        $observer->method('isValid')->willReturn(false); // Invalid = major penalty
        
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([$observer]);
        $report->method('getPlugins')->willReturn([]);
        
        // Invalid observer should significantly reduce score
        $report->expects($this->once())
            ->method('setScore')
            ->with($this->lessThan(95));

        $this->calculator->calculateOverallScore($report);
    }

    public function testDeepPluginChainPenalty(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getChainDepth')->willReturn(5); // Deep chain
        $plugin->method('getPluginType')->willReturn('around');
        $plugin->method('getScore')->willReturn(9);
        
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([$plugin]);
        
        // Deep chain should significantly reduce score
        $report->expects($this->once())
            ->method('setScore')
            ->with($this->lessThan(95));

        $this->calculator->calculateOverallScore($report);
    }

    public function testAroundPluginPenalty(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getChainDepth')->willReturn(1);
        $plugin->method('getPluginType')->willReturn('around');
        $plugin->method('getScore')->willReturn(8); // High-impact plugin
        
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([$plugin]);
        
        // Around plugins on core classes should reduce score
        $report->expects($this->once())
            ->method('setScore')
            ->with($this->lessThan(100));

        $this->calculator->calculateOverallScore($report);
    }

    public function testScoreNeverGoesBelowZero(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        
        // Create scenario with massive penalties
        $modules = array_fill(0, 300, $this->createMock(ModuleDataInterface::class));
        
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('isHighFrequency')->willReturn(true);
        $observer->method('isValid')->willReturn(false);
        $observers = array_fill(0, 1000, $observer);
        
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getChainDepth')->willReturn(10);
        $plugin->method('getPluginType')->willReturn('around');
        $plugin->method('getScore')->willReturn(10);
        $plugins = array_fill(0, 500, $plugin);
        
        $report->method('getModules')->willReturn($modules);
        $report->method('getObservers')->willReturn($observers);
        $report->method('getPlugins')->willReturn($plugins);
        
        // Score should be capped at 0
        $report->expects($this->once())
            ->method('setScore')
            ->with($this->greaterThanOrEqual(0));

        $this->calculator->calculateOverallScore($report);
    }

    public function testScoreNeverGoesAbove100(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([]);
        
        // Perfect scenario should give 100
        $report->expects($this->once())
            ->method('setScore')
            ->with(100);

        $this->calculator->calculateOverallScore($report);
    }
}
