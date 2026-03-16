<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Block\Adminhtml;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    protected $_template = 'BetterMagento_ModuleAudit::dashboard.phtml';

    private ?AuditReportInterface $report = null;

    public function __construct(
        Context $context,
        private readonly AuditRunnerInterface $auditRunner,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getReport(): AuditReportInterface
    {
        if ($this->report === null) {
            $this->report = $this->auditRunner->execute();
        }
        return $this->report;
    }

    public function getGradeColor(string $grade): string
    {
        return match ($grade) {
            'A' => '#22c55e',
            'B' => '#84cc16',
            'C' => '#eab308',
            'D' => '#f97316',
            'E' => '#ef4444',
            default => '#dc2626',
        };
    }

    public function getExportUrl(string $format): string
    {
        return $this->getUrl('bm_audit/dashboard/export', ['format' => $format]);
    }
}
