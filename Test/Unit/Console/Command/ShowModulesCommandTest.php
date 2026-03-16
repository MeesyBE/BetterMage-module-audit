<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Console\Command\ShowModulesCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ShowModulesCommandTest extends TestCase
{
    private AuditRunnerInterface&MockObject $auditRunner;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
        $command = new ShowModulesCommand($this->auditRunner);

        $app = new Application();
        $app->add($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createModule(string $name, int $score = 3, bool $enabled = true): ModuleDataInterface&MockObject
    {
        $module = $this->createMock(ModuleDataInterface::class);
        $module->method('getName')->willReturn($name);
        $module->method('getVersion')->willReturn('1.0.0');
        $module->method('isEnabled')->willReturn($enabled);
        $module->method('getScore')->willReturn($score);
        $module->method('hasRoutes')->willReturn(true);
        $module->method('hasObservers')->willReturn(false);
        $module->method('hasPlugins')->willReturn(true);
        $module->method('hasCron')->willReturn(false);
        $module->method('getRecommendation')->willReturn('');

        return $module;
    }

    private function createReport(array $modules = []): AuditReportInterface&MockObject
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getModules')->willReturn($modules);
        $report->method('getScore')->willReturn(75);
        $report->method('getGrade')->willReturn('C');

        return $report;
    }

    public function testSuccessfulExecution(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([$this->createModule('Vendor_ModuleA')])
        );

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Vendor_ModuleA', $display);
        $this->assertStringContainsString('75/100', $display);
    }

    public function testFilterByName(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createModule('Vendor_ModuleA'),
                $this->createModule('Other_ModuleB'),
            ])
        );

        $exitCode = $this->commandTester->execute(['--filter' => 'Vendor']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Vendor_ModuleA', $display);
        $this->assertStringNotContainsString('Other_ModuleB', $display);
    }

    public function testMinScoreFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createModule('HighScore_Module', 8),
                $this->createModule('LowScore_Module', 2),
            ])
        );

        $exitCode = $this->commandTester->execute(['--min-score' => '5']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('HighScore_Module', $display);
        $this->assertStringNotContainsString('LowScore_Module', $display);
    }

    public function testEnabledOnlyFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createModule('Enabled_Module', 3, true),
                $this->createModule('Disabled_Module', 3, false),
            ])
        );

        $exitCode = $this->commandTester->execute(['--enabled-only' => true]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Enabled_Module', $display);
        $this->assertStringNotContainsString('Disabled_Module', $display);
    }

    public function testExceptionReturnsFailure(): void
    {
        $this->auditRunner->method('execute')
            ->willThrowException(new \RuntimeException('Scan error'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Scan error', $this->commandTester->getDisplay());
    }
}
