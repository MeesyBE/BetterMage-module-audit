<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Console\Command;

use BetterMagento\ModuleAudit\Console\Command\ShowRoutesCommand;
use BetterMagento\ModuleAudit\Model\Audit\RouteAnalyzer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ShowRoutesCommandTest extends TestCase
{
    private RouteAnalyzer&MockObject $routeAnalyzer;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->routeAnalyzer = $this->createMock(RouteAnalyzer::class);
        $command = new ShowRoutesCommand($this->routeAnalyzer);

        $app = new Application();
        $app->add($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createAnalysisResult(array $routes = [], array $duplicates = [], array $orphaned = []): array
    {
        return [
            'routes' => $routes,
            'duplicates' => $duplicates,
            'orphaned' => $orphaned,
            'stats' => [
                'total' => count($routes),
                'frontend' => count(array_filter($routes, fn($r) => $r['scope'] === 'frontend')),
                'adminhtml' => count(array_filter($routes, fn($r) => $r['scope'] === 'adminhtml')),
            ],
        ];
    }

    public function testSuccessfulExecution(): void
    {
        $this->routeAnalyzer->method('analyze')->willReturn(
            $this->createAnalysisResult([
                [
                    'module' => 'Vendor_Module',
                    'scope' => 'frontend',
                    'id' => 'vendor_module',
                    'front_name' => 'vendor',
                    'has_controllers' => true,
                ],
            ])
        );

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Route Report', $display);
        $this->assertStringContainsString('Vendor_Module', $display);
    }

    public function testScopeFilter(): void
    {
        $this->routeAnalyzer->method('analyze')->willReturn(
            $this->createAnalysisResult([
                ['module' => 'Frontend_Module', 'scope' => 'frontend', 'id' => 'fm', 'front_name' => 'fm', 'has_controllers' => true],
                ['module' => 'Admin_Module', 'scope' => 'adminhtml', 'id' => 'am', 'front_name' => 'am', 'has_controllers' => true],
            ])
        );

        $exitCode = $this->commandTester->execute(['--scope' => 'frontend']);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Frontend_Module', $display);
        $this->assertStringNotContainsString('Admin_Module', $display);
    }

    public function testDuplicatesMode(): void
    {
        $this->routeAnalyzer->method('analyze')->willReturn([
            'routes' => [],
            'duplicates' => ['catalog' => ['Magento_Catalog', 'Vendor_Catalog']],
            'orphaned' => [],
            'stats' => ['total' => 0, 'frontend' => 0, 'adminhtml' => 0],
        ]);

        $exitCode = $this->commandTester->execute(['--duplicates' => true]);

        $this->assertSame(0, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Duplicate', $display);
        $this->assertStringContainsString('catalog', $display);
    }

    public function testNoRoutesMessage(): void
    {
        $this->routeAnalyzer->method('analyze')->willReturn(
            $this->createAnalysisResult()
        );

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No routes matching filters', $this->commandTester->getDisplay());
    }

    public function testExceptionReturnsFailure(): void
    {
        $this->routeAnalyzer->method('analyze')
            ->willThrowException(new \RuntimeException('Route analysis failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Route analysis failed', $this->commandTester->getDisplay());
    }
}
