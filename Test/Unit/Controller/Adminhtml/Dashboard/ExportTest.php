<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Controller\Adminhtml\Dashboard;

use BetterMagento\ModuleAudit\Controller\Adminhtml\Dashboard\Export;
use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Export\ExporterInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    private AuditRunnerInterface&MockObject $auditRunner;
    private FileFactory&MockObject $fileFactory;
    private ExporterInterface&MockObject $jsonExporter;
    private ExporterInterface&MockObject $htmlExporter;
    private RequestInterface&MockObject $request;

    protected function setUp(): void
    {
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
        $this->fileFactory = $this->createMock(FileFactory::class);
        $this->jsonExporter = $this->createMock(ExporterInterface::class);
        $this->htmlExporter = $this->createMock(ExporterInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    public function testAdminResourceConstant(): void
    {
        $this->assertSame('BetterMagento_ModuleAudit::audit_view', Export::ADMIN_RESOURCE);
    }

    public function testJsonExporterIsUsedByDefault(): void
    {
        $this->request->method('getParam')
            ->with('format', 'json')
            ->willReturn('json');

        $report = $this->createMock(AuditReportInterface::class);
        $this->auditRunner->method('execute')->willReturn($report);

        $this->jsonExporter->expects($this->once())->method('export')->with($report)->willReturn('{}');
        $this->jsonExporter->method('getFileExtension')->willReturn('json');
        $this->jsonExporter->method('getMimeType')->willReturn('application/json');

        $this->htmlExporter->expects($this->never())->method('export');

        $expectedResult = $this->createMock(ResultInterface::class);
        $this->fileFactory->method('create')->willReturn($expectedResult);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        $controller = new Export(
            $context,
            $this->auditRunner,
            $this->fileFactory,
            $this->jsonExporter,
            $this->htmlExporter,
        );

        $result = $controller->execute();
        $this->assertSame($expectedResult, $result);
    }

    public function testHtmlExporterUsedForHtmlFormat(): void
    {
        $this->request->method('getParam')
            ->with('format', 'json')
            ->willReturn('html');

        $report = $this->createMock(AuditReportInterface::class);
        $this->auditRunner->method('execute')->willReturn($report);

        $this->htmlExporter->expects($this->once())->method('export')->with($report)->willReturn('<html></html>');
        $this->htmlExporter->method('getFileExtension')->willReturn('html');
        $this->htmlExporter->method('getMimeType')->willReturn('text/html');

        $this->jsonExporter->expects($this->never())->method('export');

        $expectedResult = $this->createMock(ResultInterface::class);
        $this->fileFactory->method('create')->willReturn($expectedResult);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        $controller = new Export(
            $context,
            $this->auditRunner,
            $this->fileFactory,
            $this->jsonExporter,
            $this->htmlExporter,
        );

        $result = $controller->execute();
        $this->assertSame($expectedResult, $result);
    }
}
