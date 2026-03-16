<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Api;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;

/**
 * Orchestrates the complete module audit scan: modules, observers, plugins, scoring.
 *
 * Usage:
 *   $runner = $objectManager->get(AuditRunnerInterface::class);
 *   $report = $runner->execute();
 */
interface AuditRunnerInterface
{
    /**
     * Execute the full audit scan.
     *
     * @return AuditReportInterface Complete audit data (modules, observers, plugins, scores)
     */
    public function execute(): AuditReportInterface;
}
