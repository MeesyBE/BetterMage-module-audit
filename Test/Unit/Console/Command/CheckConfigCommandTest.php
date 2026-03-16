<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Console\Command;

use BetterMagento\ModuleAudit\Console\Command\CheckConfigCommand;
use BetterMagento\ModuleAudit\Model\Audit\ConfigUsageChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CheckConfigCommandTest extends TestCase
{
    private ConfigUsageChecker&MockObject $configChecker;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->configChecker = $this->createMock(ConfigUsageChecker::class);
        $command = new CheckConfigCommand($this->configChecker);

        $app = new Application();
        $app->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testSuccessfulAnalysis(): void
    {
        $this->configChecker->method('analyze')->willReturn([
            'modules' => [
                'Vendor_Module' => [
                    'defined_paths' => ['path/a', 'path/b'],
                    'used_paths' => ['path/a'],
                    'unused_paths' => ['path/b'],
                    'has_system_xml' => true,
                ],
            ],
            'stats' => [
                'modules_with_config' => 1,
                'total_paths' => 2,
                'used_paths' => 1,
                'unused_paths' => 1,
                'modules_with_unused' => 1,
            ],
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Config Usage Report', $display);
        $this->assertStringContainsString('Vendor_Module', $display);
    }

    public function testJsonFormatOutput(): void
    {
        $this->configChecker->method('analyze')->willReturn([
            'modules' => [],
            'stats' => [
                'modules_with_config' => 0,
                'total_paths' => 0,
                'used_paths' => 0,
                'unused_paths' => 0,
                'modules_with_unused' => 0,
            ],
        ]);

        $exitCode = $this->commandTester->execute(['--format' => 'json']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('"stats"', $display);
    }

    public function testModuleFilterOption(): void
    {
        $this->configChecker->method('analyze')->willReturn([
            'modules' => [
                'Vendor_ModuleA' => [
                    'defined_paths' => ['a'],
                    'used_paths' => ['a'],
                    'unused_paths' => [],
                    'has_system_xml' => true,
                ],
                'Other_ModuleB' => [
                    'defined_paths' => ['b'],
                    'used_paths' => [],
                    'unused_paths' => ['b'],
                    'has_system_xml' => false,
                ],
            ],
            'stats' => [
                'modules_with_config' => 2,
                'total_paths' => 2,
                'used_paths' => 1,
                'unused_paths' => 1,
                'modules_with_unused' => 1,
            ],
        ]);

        $exitCode = $this->commandTester->execute(['--module' => 'Vendor']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Vendor_ModuleA', $display);
        $this->assertStringNotContainsString('Other_ModuleB', $display);
    }

    public function testNoIssuesFoundMessage(): void
    {
        $this->configChecker->method('analyze')->willReturn([
            'modules' => [],
            'stats' => [
                'modules_with_config' => 0,
                'total_paths' => 0,
                'used_paths' => 0,
                'unused_paths' => 0,
                'modules_with_unused' => 0,
            ],
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No config issues found', $this->commandTester->getDisplay());
    }

    public function testExceptionReturnsFailure(): void
    {
        $this->configChecker->method('analyze')
            ->willThrowException(new \RuntimeException('Scan failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Scan failed', $this->commandTester->getDisplay());
    }
}
