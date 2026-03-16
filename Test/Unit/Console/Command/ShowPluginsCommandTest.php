<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Console\Command\ShowPluginsCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ShowPluginsCommandTest extends TestCase
{
    private AuditRunnerInterface&MockObject $auditRunner;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
        $command = new ShowPluginsCommand($this->auditRunner);

        $app = new Application();
        $app->add($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createPlugin(
        string $module = 'Vendor_Module',
        string $type = 'around',
        int $chainDepth = 1,
        int $score = 3,
    ): PluginDataInterface&MockObject {
        $plugin = $this->createMock(PluginDataInterface::class);
        $plugin->method('getModuleName')->willReturn($module);
        $plugin->method('getInterceptedClass')->willReturn('Magento\\Catalog\\Model\\Product');
        $plugin->method('getInterceptedMethod')->willReturn('getName');
        $plugin->method('getPluginClass')->willReturn('Vendor\\Module\\Plugin\\ProductPlugin');
        $plugin->method('getPluginType')->willReturn($type);
        $plugin->method('getSortOrder')->willReturn(100);
        $plugin->method('getChainDepth')->willReturn($chainDepth);
        $plugin->method('getScore')->willReturn($score);

        return $plugin;
    }

    private function createReport(array $plugins = []): AuditReportInterface&MockObject
    {
        $report = $this->createMock(AuditReportInterface::class);
        $report->method('getPlugins')->willReturn($plugins);
        $report->method('getStatistics')->willReturn([
            'total_plugins' => count($plugins),
            'around_plugins' => 0,
            'deep_chains' => 0,
        ]);

        return $report;
    }

    public function testSuccessfulExecution(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([$this->createPlugin()])
        );

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Plugin Report', $display);
        $this->assertStringContainsString('Vendor_Module', $display);
    }

    public function testTypeFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createPlugin('Around_Module', 'around'),
                $this->createPlugin('Before_Module', 'before'),
            ])
        );

        $exitCode = $this->commandTester->execute(['--type' => 'before']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Before_Module', $display);
        $this->assertStringNotContainsString('Around_Module', $display);
    }

    public function testDeepChainsFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createPlugin('Shallow_Module', 'around', 2),
                $this->createPlugin('Deep_Module', 'around', 5),
            ])
        );

        $exitCode = $this->commandTester->execute(['--deep-chains' => true]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Deep_Module', $display);
        $this->assertStringNotContainsString('Shallow_Module', $display);
    }

    public function testModuleFilter(): void
    {
        $this->auditRunner->method('execute')->willReturn(
            $this->createReport([
                $this->createPlugin('Vendor_PluginA'),
                $this->createPlugin('Other_PluginB'),
            ])
        );

        $exitCode = $this->commandTester->execute(['--module' => 'Vendor']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Vendor_PluginA', $display);
        $this->assertStringNotContainsString('Other_PluginB', $display);
    }

    public function testExceptionReturnsFailure(): void
    {
        $this->auditRunner->method('execute')
            ->willThrowException(new \RuntimeException('Plugin scan failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Plugin scan failed', $this->commandTester->getDisplay());
    }
}
