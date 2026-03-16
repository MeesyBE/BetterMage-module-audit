<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Api\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;

/**
 * Interface for audit report exporters.
 *
 * Exporters convert AuditReport objects into various output formats
 * (JSON, HTML, PDF, etc.) for different use cases.
 */
interface ExporterInterface
{
    /**
     * Export audit report to string representation.
     *
     * @param AuditReportInterface $report The audit report to export
     * @return string The exported report in the target format
     * @throws \Exception If export fails
     */
    public function export(AuditReportInterface $report): string;

    /**
     * Get the MIME type for this export format.
     *
     * @return string MIME type (e.g., 'application/json', 'text/html')
     */
    public function getMimeType(): string;

    /**
     * Get the file extension for this export format.
     *
     * @return string File extension without dot (e.g., 'json', 'html')
     */
    public function getFileExtension(): string;
}
