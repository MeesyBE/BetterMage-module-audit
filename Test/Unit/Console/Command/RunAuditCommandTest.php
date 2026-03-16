<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Console\Command\RunAuditCommand;
use BetterMagento\ModuleAudit\Model\Export\HtmlExporter;
use BetterMagento\ModuleAudit\Model\Export\JsonExporter;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RunAuditCommandTest extends TestCase
{
    private AuditRunnerInterface&MockObject $auditRunner;
    private JsonExporter&MockObject $jsonExporter;
    private HtmlExporter&MockObject $htmlExporter;
    private File&MockObject $fileDriver;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
        $this->jsonExporter = $this->createMock(JsonExporter::class);
        $this->htmlExporter = $this->createMock(HtmlExporter::class);
        $this->fileDriver = $this->createMock(File::class);

        $command = new RunAuditCommand(
            $this->auditRunner,
            $this->jsonExporter,
            $this->htmlExporter,
            $this->fileDriver,
        );

        $app = new Application();
        $app->add($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createReport(): AuditReportInterface&MockObject
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getScore')->willReturn(85);
        $report->method('getGrade')->willReturn('B');
        $report->method('getExecutedAt')->willReturn('2026-01-01T00:00:00+00:00');
        $report->method('getStatistics')->willReturn([
            'total_modules' => 10,
            'enabled_modules' => 8,
            'modules_with_routes' => 3,
            'modules_with_observers' => 5,
            'modules_with_plugins' => 4,
            'modules_with_cron' => 2,
            'total_observers' => 20,
            'high_frequency_observers' => 1,
            'invalid_observers' => 0,
            'total_plugins' => 15,
            'around_plugins' => 5,
            'deep_chains' => 0,
        ]);
        $report->method('getModules')->willReturn([]);
        $report->method('getObservers')->willReturn([]);
        $report->method('getPlugins')->willReturn([]);

        return $report;
    }

    public function testCliOutputDefault(): void
    {
        $this->auditRunner->method('execute')->willReturn($this->createReport());

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Module Audit', $display);
        $this->assertStringContainsString('85/100', $display);
        $this->assertStringContainsString('Audit completed successfully', $display);
    }

    public function testJsonOutputToStdout(): void
    {
        $report = $this->createReport();
        $this->auditRunner->method('execute')->willReturn($report);
        $this->jsonExporter->method('export')->willReturn('{"score":85}');

        $exitCode = $this->commandTester->execute(['--output' => 'json']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('{"score":85}', $this->commandTester->getDisplay());
    }

    public function testJsonOutputToFile(): void
    {
        $report = $this->createReport();
        $this->auditRunner->method('execute')->willReturn($report);
        $this->jsonExporter->method('export')->willReturn('{"score":85}');

        $this->fileDriver->expects($this->once())
            ->method('filePutContents')
            ->with('report.json', '{"score":85}');

        $exitCode = $this->commandTester->execute([
            '--output' => 'json',
            '--file' => 'report.json',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('report.json', $this->commandTester->getDisplay());
    }

    public function testHtmlOutputRequiresFileOption(): void
    {
        $report = $this->createReport();
        $this->auditRunner->method('execute')->willReturn($report);

        $exitCode = $this->commandTester->execute(['--output' => 'html']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('--file option', $this->commandTester->getDisplay());
    }

    public function testExceptionReturnsFailure(): void
    {
        $this->auditRunner->method('execute')
            ->willThrowException(new \RuntimeException('Audit scan failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Audit scan failed', $this->commandTester->getDisplay());
    }
}
