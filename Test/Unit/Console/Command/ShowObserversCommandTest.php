<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Console\Command\ShowObserversCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ShowObserversCommandTest extends TestCase
{
    private AuditRunnerInterface&MockObject $auditRunner;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
        $command = new ShowObserversCommand($this->auditRunner);

        $app = new Application();
        $app->add($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createObserver(
        string $module = 'Vendor_Module',
        string $event = 'catalog_product_save_after',
        bool $highFrequency = false,
        bool $valid = true,
        int $score = 3,
    ): ObserverDataInterface&MockObject {
        $observer = $this->createMock(ObserverDataInterface::class);
        $observer->method('getModuleName')->willReturn($module);
        $observer->method('getEventName')->willReturn($event);
        $observer->method('getObserverClass')->willReturn('Vendor\\Module\\Observer\\TestObserver');
        $observer->method('getObserverMethod')->willReturn('execute');
        $observer->method('isHighFrequency')->willReturn($highFrequency);
        $observer->method('isValid')->willReturn($valid);
        $observer->method('getScore')->willReturn($score);
        $observer->method('getScope')->willReturn('frontend');

        return $observer;
    }

    private function createReport(array $observers = []): AuditReportInterface&MockObject
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getObservers')->willReturn($observers);
        $report->method('getStatistics')->willReturn([
            'total_observers' => count($observers),
            'high_frequency_observers' => 0,
            'invalid_observers' => 0,
        ]);

        return $report;
    }

    public function testSuccessfulExecution(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([$this->createObserver()])
        );

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Observer Report', $display);
        $this->assertStringContainsString('Vendor_Module', $display);
    }

    public function testHighFrequencyFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createObserver('Normal_Module', 'some_event', false),
                $this->createObserver('Hot_Module', 'checkout_submit_all_after', true),
            ])
        );

        $exitCode = $this->commandTester->execute(['--high-frequency' => true]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Hot_Module', $display);
        $this->assertStringNotContainsString('Normal_Module', $display);
    }

    public function testInvalidFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createObserver('Valid_Module', 'event_a', false, true),
                $this->createObserver('Invalid_Module', 'event_b', false, false),
            ])
        );

        $exitCode = $this->commandTester->execute(['--invalid' => true]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid_Module', $display);
        $this->assertStringNotContainsString('Valid_Module', $display);
    }

    public function testModuleFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createObserver('Vendor_ModuleA'),
                $this->createObserver('Other_ModuleB'),
            ])
        );

        $exitCode = $this->commandTester->execute(['--module' => 'Vendor']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Vendor_ModuleA', $display);
        $this->assertStringNotContainsString('Other_ModuleB', $display);
    }

    public function testExceptionReturnsFailure(): void
    {
        $this->auditRunner->method('execute')
            ->willThrowException(new \RuntimeException('Observer scan failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Observer scan failed', $this->commandTester->getDisplay());
    }
}
