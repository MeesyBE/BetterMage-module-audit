<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Data;

use BetterMagento\ModuleAudit\Model\Data\AuditReport;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Data\AuditReport
 */
class AuditReportTest extends TestCase
{
    private AuditReport $report;

    protected function setUp(): void
    {
        $this->report = new AuditReport();
    }

    public function testDefaults(): void
    {
        self::assertSame(0, $this->report->getScore());
        self::assertSame('F', $this->report->getGrade());
        self::assertSame('', $this->report->getExecutedAt());
        self::assertSame([], $this->report->getModules());
        self::assertSame([], $this->report->getObservers());
        self::assertSame([], $this->report->getPlugins());
        self::assertSame([], $this->report->getStatistics());
    }

    public function testSetAndGetScore(): void
    {
        $this->report->setScore(85);
        self::assertSame(85, $this->report->getScore());
    }

    public function testSetAndGetGrade(): void
    {
        $this->report->setGrade('A');
        self::assertSame('A', $this->report->getGrade());
    }

    public function testSetAndGetExecutedAt(): void
    {
        $this->report->setExecutedAt('2026-02-28T12:00:00+00:00');
        self::assertSame('2026-02-28T12:00:00+00:00', $this->report->getExecutedAt());
    }

    public function testSetAndGetModules(): void
    {
        $modules = [$this->createMock(\BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface::class)];
        $this->report->setModules($modules);
        self::assertCount(1, $this->report->getModules());
    }

    public function testSetAndGetObservers(): void
    {
        $observers = [$this->createMock(\BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface::class)];
        $this->report->setObservers($observers);
        self::assertCount(1, $this->report->getObservers());
    }

    public function testSetAndGetPlugins(): void
    {
        $plugins = [$this->createMock(\BetterMagento\ModuleAudit\Api\Data\PluginDataInterface::class)];
        $this->report->setPlugins($plugins);
        self::assertCount(1, $this->report->getPlugins());
    }

    public function testSetAndGetStatistics(): void
    {
        $stats = ['total_modules' => 42, 'risk_level' => 'low'];
        $this->report->setStatistics($stats);
        self::assertSame($stats, $this->report->getStatistics());
    }

    public function testScoreOverwrite(): void
    {
        $this->report->setScore(50);
        $this->report->setScore(99);
        self::assertSame(99, $this->report->getScore());
    }
}
