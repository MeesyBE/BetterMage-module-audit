<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Controller\Adminhtml\Dashboard;

use BetterMagento\ModuleAudit\Controller\Adminhtml\Dashboard\Index;
use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private Context&MockObject $context;
    private PageFactory&MockObject $pageFactory;
    private AuditRunnerInterface&MockObject $auditRunner;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->pageFactory = $this->createMock(PageFactory::class);
        $this->auditRunner = $this->createMock(AuditRunnerInterface::class);
    }

    public function testAdminResourceConstant(): void
    {
        $this->assertSame('BetterMagento_ModuleAudit::audit_view', Index::ADMIN_RESOURCE);
    }

    public function testExecuteReturnsConfiguredPage(): void
    {
        $title = $this->createMock(Title::class);
        $title->expects($this->once())->method('prepend');

        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn($title);

        $page = $this->createMock(Page::class);
        $page->expects($this->once())
            ->method('setActiveMenu')
            ->with('BetterMagento_ModuleAudit::audit')
            ->willReturnSelf();
        $page->method('getConfig')->willReturn($config);

        $this->pageFactory->method('create')->willReturn($page);

        $controller = new Index(
            $this->context,
            $this->pageFactory,
            $this->auditRunner,
        );

        $result = $controller->execute();

        $this->assertSame($page, $result);
    }
}
