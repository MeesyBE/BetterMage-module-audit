<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Audit\ScoreCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for ScoreCalculator.
 *
 * Tests boundary conditions at grade thresholds, penalty caps, and extreme inputs.
 */
class ScoreCalculatorEdgeCaseTest extends TestCase
{
    private ScoreCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ScoreCalculator();
    }

    // --- Grade boundary tests ---

    public function testGradeBoundaryAt90IsA(): void
    {
        $report = $this->createReportMock([], [], []);
        $report->expects(self::once())->method('setGrade')->with('A');
        // Empty arrays = score 100 (no penalties)
        $this->calculator->calculateOverallScore($report);
    }

    public function testGradeBoundaryAt80IsB(): void
    {
        // Need exactly 11-20 points of penalties to get 80-89 → grade B
        // 10 modules with score >= 3 → 10 * 2 = 20 penalty → score 80
        $modules = $this->createModulesWithScore(10, 3);
        $report = $this->createReportMock($modules, [], []);

        $report->expects(self::once())->method('setGrade')->with('B');
        $report->expects(self::once())->method('setScore')->with(80);

        $this->calculator->calculateOverallScore($report);
    }

    public function testGradeBoundaryAt70IsC(): void
    {
        // 15 modules * 2 = 30 penalty → score 70
        $modules = $this->createModulesWithScore(15, 3);
        $report = $this->createReportMock($modules, [], []);

        $report->expects(self::once())->method('setGrade')->with('C');
        $report->expects(self::once())->method('setScore')->with(70);

        $this->calculator->calculateOverallScore($report);
    }

    public function testGradeBoundaryAt50IsE(): void
    {
        // Need 50 penalty points: 25 modules * 2 = 50 → score 50
        $modules = $this->createModulesWithScore(25, 3);
        $report = $this->createReportMock($modules, [], []);

        $report->expects(self::once())->method('setGrade')->with('E');
        $report->expects(self::once())->method('setScore')->with(50);

        $this->calculator->calculateOverallScore($report);
    }

    public function testGradeBelow50IsF(): void
    {
        // 30 modules * 2 = 60 penalty → score 40 → F
        $modules = $this->createModulesWithScore(30, 3);
        $report = $this->createReportMock($modules, [], []);

        $report->expects(self::once())->method('setGrade')->with('F');

        $this->calculator->calculateOverallScore($report);
    }

    // --- Score floor at 0 ---

    public function testScoreNeverGoesBelowZero(): void
    {
        // Extreme: 100 modules with high score + 200+ modules count penalty
        $modules = array_merge(
            $this->createModulesWithScore(60, 5),
            $this->createModulesWithScore(150, 0)  // 210 total → +10 penalty
        );
        // 60*2 + 10 = 130 penalty → capped at 0
        $observers = $this->createObservers(100, true, true); // max 40 cap
        $plugins = $this->createPlugins(100, 4, 'around', 8); // max 40 cap

        $report = $this->createReportMock($modules, $observers, $plugins);
        $report->expects(self::once())->method('setScore')->with(0);

        $this->calculator->calculateOverallScore($report);
    }

    // --- Observer penalty cap ---

    public function testObserverPenaltyCappedAt40(): void
    {
        // Many broken, high-frequency observers should not exceed 40
        $observers = $this->createObservers(600, true, false);
        // 600 high-freq * 3 = 1800, + count>500 = 10 → cap 40
        $report = $this->createReportMock([], $observers, []);

        $report->expects(self::once())->method('setScore')
            ->with(self::greaterThanOrEqual(60)); // 100 - 40 = 60

        $this->calculator->calculateOverallScore($report);
    }

    // --- Plugin penalty cap ---

    public function testPluginPenaltyCappedAt40(): void
    {
        $plugins = $this->createPlugins(400, 5, 'around', 8);
        $report = $this->createReportMock([], [], $plugins);

        $report->expects(self::once())->method('setScore')
            ->with(self::greaterThanOrEqual(60)); // 100 - 40 = 60

        $this->calculator->calculateOverallScore($report);
    }

    // --- Module count thresholds ---

    public function testOver200ModulesAdds10Penalty(): void
    {
        $modules = $this->createModulesWithScore(201, 0); // No individual penalties
        $report = $this->createReportMock($modules, [], []);

        $report->expects(self::once())->method('setScore')->with(90); // 100 - 10

        $this->calculator->calculateOverallScore($report);
    }

    public function testOver150ModulesAdds5Penalty(): void
    {
        $modules = $this->createModulesWithScore(155, 0);
        $report = $this->createReportMock($modules, [], []);

        $report->expects(self::once())->method('setScore')->with(95); // 100 - 5

        $this->calculator->calculateOverallScore($report);
    }

    // --- calculateModuleScores ---

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

        $module->expects(self::once())->method('setScore')->with(5); // 3 + 2
        $module->expects(self::once())->method('setRecommendation')
            ->with('Review module necessity and configuration');

        $this->calculator->calculateModuleScores([$module]);
    }

    public function testCalculateModuleScoresForActiveModule(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('hasRoutes')->willReturn(true);
        $module->method('hasObservers')->willReturn(true);
        $module->method('hasPlugins')->willReturn(true);
        $module->method('hasCron')->willReturn(false);
        $module->method('isEnabled')->willReturn(true);
        $module->method('hasDatabase')->willReturn(true);
        $module->method('hasConfig')->willReturn(true);

        $module->expects(self::once())->method('setScore')->with(0);
        $module->expects(self::once())->method('setRecommendation')
            ->with('Module appears to be properly utilized');

        $this->calculator->calculateModuleScores([$module]);
    }

    public function testCalculateModuleScoresCapAt10(): void
    {
        // Even with multiple penalties, individual module score caps at 10
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('hasRoutes')->willReturn(false);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('isEnabled')->willReturn(true);
        $module->method('hasDatabase')->willReturn(false);
        $module->method('hasConfig')->willReturn(false);

        $module->expects(self::once())->method('setScore')
            ->with(self::lessThanOrEqual(10));

        $this->calculator->calculateModuleScores([$module]);
    }

    public function testCalculateModuleScoresHighScoreRecommendsRemoval(): void
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('hasRoutes')->willReturn(false);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(false);
        $module->method('hasCron')->willReturn(false);
        $module->method('isEnabled')->willReturn(true);
        $module->method('hasDatabase')->willReturn(false);
        $module->method('hasConfig')->willReturn(false);

        // Score will be 5 which triggers "Review" recommendation (>= 4)
        $module->expects(self::once())->method('setRecommendation')
            ->with('Review module necessity and configuration');

        $this->calculator->calculateModuleScores([$module]);
    }

    // --- Helpers ---

    private function createReportMock(array $modules, array $observers, array $plugins): AuditReportInterface|MockObject
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getModules')->willReturn($modules);
        $report->method('getObservers')->willReturn($observers);
        $report->method('getPlugins')->willReturn($plugins);
        return $report;
    }

    private function createModulesWithScore(int $count, int $score): array
    {
        $modules = [];
        for ($i = 0; $i < $count; $i++) {
            $module = $this->createMock(ModuleDataInterface::class);
            $module->method('getScore')->willReturn($score);
            $modules[] = $module;
        }
        return $modules;
    }

    private function createObservers(int $count, bool $highFrequency, bool $invalid): array
    {
        $observers = [];
        for ($i = 0; $i < $count; $i++) {
            $observer = $this->createMock(ObserverDataInterface::class);
            $observer->method('isHighFrequency')->willReturn($highFrequency);
            $observer->method('isValid')->willReturn(!$invalid);
            $observers[] = $observer;
        }
        return $observers;
    }

    private function createPlugins(int $count, int $chainDepth, string $type, int $score): array
    {
        $plugins = [];
        for ($i = 0; $i < $count; $i++) {
            $plugin = $this->createMock(PluginDataInterface::class);
            $plugin->method('getChainDepth')->willReturn($chainDepth);
            $plugin->method('getPluginType')->willReturn($type);
            $plugin->method('getScore')->willReturn($score);
            $plugins[] = $plugin;
        }
        return $plugins;
    }
}
