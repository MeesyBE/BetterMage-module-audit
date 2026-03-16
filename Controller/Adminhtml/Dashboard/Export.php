<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Controller\Adminhtml\Dashboard;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Export\ExporterInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;

class Export extends Action
{
    public const ADMIN_RESOURCE = 'BetterMagento_ModuleAudit::audit_view';

    public function __construct(
        Context $context,
        private readonly AuditRunnerInterface $auditRunner,
        private readonly FileFactory $fileFactory,
        private readonly ExporterInterface $jsonExporter,
        private readonly ExporterInterface $htmlExporter,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $format = $this->getRequest()->getParam('format', 'json');
        $report = $this->auditRunner->execute();

        $exporter = $format === 'html' ? $this->htmlExporter : $this->jsonExporter;
        $content = $exporter->export($report);
        $filename = 'audit-report-' . date('Y-m-d-His') . '.' . $exporter->getFileExtension();

        return $this->fileFactory->create(
            $filename,
            $content,
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
            $exporter->getMimeType()
        );
    }
}
