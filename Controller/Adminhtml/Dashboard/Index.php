<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Controller\Adminhtml\Dashboard;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'BetterMagento_ModuleAudit::audit_view';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly AuditRunnerInterface $auditRunner,
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('BetterMagento_ModuleAudit::audit');
        $resultPage->getConfig()->getTitle()->prepend(__('Module Audit Dashboard'));

        return $resultPage;
    }
}
