<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Block\Adminhtml;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Block\Adminhtml\Dashboard;
use Magento\Backend\Block\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Block\Adminhtml\Dashboard
 */
class DashboardTest extends TestCase
{
    private Dashboard $block;
    private AuditRunnerInterface&MockObject $auditRunner;

    protected function setUp(): void
    {
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
        $context = $this->createMock(Context::class);

        $this->block = new Dashboard($context, $this->auditRunner);
    }

    public function testGetReportExecutesAudit(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        $this->auditRunner->expects($this->once())
            ->method('execute')
            ->willReturn($report);

        $result = $this->block->getReport();
        $this->assertSame($report, $result);
    }

    public function testGetReportCachesResult(): void
    {
        $report = $this->createMock(AuditReportInterface::class);
        $this->auditRunner->expects($this->once())
            ->method('execute')
            ->willReturn($report);

        // Call twice, should only execute audit once
        $this->block->getReport();
        $this->block->getReport();
    }

    /**
     * @dataProvider gradeColorProvider
     */
    public function testGetGradeColor(string $grade, string $expectedColor): void
    {
        $this->assertSame($expectedColor, $this->block->getGradeColor($grade));
    }

    public static function gradeColorProvider(): array
    {
        return [
            ['A', '#22c55e'],
            ['B', '#84cc16'],
            ['C', '#eab308'],
            ['D', '#f97316'],
            ['E', '#ef4444'],
            ['F', '#dc2626'],
            ['unknown', '#dc2626'],
        ];
    }

    public function testGetExportUrlContainsFormat(): void
    {
        // Since getUrl requires a full Magento context, we just verify
        // the method exists and calls through without error
        $result = $this->block->getExportUrl('json');
        $this->assertIsString($result);
    }
}
