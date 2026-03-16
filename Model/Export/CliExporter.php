<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Export\ExporterInterface;

/**
 * Export audit reports as formatted plain text for CLI output.
 *
 * Produces a structured text report with sections for summary,
 * module/observer/plugin statistics, and top issues.
 */
class CliExporter implements ExporterInterface
{
    public function export(AuditReportInterface $report): string
    {
        $lines = [];
        $stats = $report->getStatistics();
        $score = $report->getScore();
        $grade = $report->getGrade();

        // Header
        $lines[] = 'BetterMagento Module Audit Report';
        $lines[] = '==================================';
        $lines[] = '';

        // Summary
        $lines[] = 'AUDIT SUMMARY';
        $lines[] = str_repeat('─', 40);
        $lines[] = sprintf('Score: %d/100 (Grade: %s)', $score, $grade);
        $lines[] = sprintf('Executed: %s', $report->getExecutedAt());
        $lines[] = '';

        // Module Statistics
        $lines[] = 'MODULE STATISTICS';
        $lines[] = str_repeat('─', 40);
        $lines[] = sprintf('  Total Modules:          %d', $stats['total_modules'] ?? 0);
        $lines[] = sprintf('  Enabled Modules:        %d', $stats['enabled_modules'] ?? 0);
        $lines[] = sprintf('  Modules with Routes:    %d', $stats['modules_with_routes'] ?? 0);
        $lines[] = sprintf('  Modules with Observers: %d', $stats['modules_with_observers'] ?? 0);
        $lines[] = sprintf('  Modules with Plugins:   %d', $stats['modules_with_plugins'] ?? 0);
        $lines[] = sprintf('  Modules with Cron:      %d', $stats['modules_with_cron'] ?? 0);
        $lines[] = '';

        // Observer Statistics
        $lines[] = 'OBSERVER STATISTICS';
        $lines[] = str_repeat('─', 40);
        $lines[] = sprintf('  Total Observers:          %d', $stats['total_observers'] ?? 0);
        $lines[] = sprintf('  High-Frequency Observers: %d', $stats['high_frequency_observers'] ?? 0);
        $lines[] = sprintf('  Invalid Observers:        %d', $stats['invalid_observers'] ?? 0);
        $lines[] = '';

        // Plugin Statistics
        $lines[] = 'PLUGIN STATISTICS';
        $lines[] = str_repeat('─', 40);
        $lines[] = sprintf('  Total Plugins:          %d', $stats['total_plugins'] ?? 0);
        $lines[] = sprintf('  Around Plugins:         %d', $stats['around_plugins'] ?? 0);
        $lines[] = sprintf('  Deep Plugin Chains (≥4): %d', $stats['deep_chains'] ?? 0);
        $lines[] = '';

        // Top Issues
        $lines[] = 'TOP ISSUES';
        $lines[] = str_repeat('─', 40);

        $issues = $this->collectIssues($report);
        if (empty($issues)) {
            $lines[] = '  No major performance issues detected.';
        } else {
            foreach (array_slice($issues, 0, 15) as $issue) {
                $lines[] = '  ' . $issue;
            }
            if (count($issues) > 15) {
                $lines[] = sprintf('  ... and %d more issues', count($issues) - 15);
            }
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }

    public function getFileExtension(): string
    {
        return 'txt';
    }

    /**
     * @return list<string>
     */
    private function collectIssues(AuditReportInterface $report): array
    {
        $issues = [];

        foreach ($report->getObservers() as $observer) {
            if ($observer->isHighFrequency() && $observer->getScore() >= 6) {
                $issues[] = sprintf(
                    '[OBSERVER] %s on "%s" (Module: %s, Score: %d)',
                    $observer->getObserverClass(),
                    $observer->getEventName(),
                    $observer->getModuleName(),
                    $observer->getScore()
                );
            }
        }

        foreach ($report->getPlugins() as $plugin) {
            if ($plugin->getScore() >= 7) {
                $issues[] = sprintf(
                    '[PLUGIN] %s intercepts %s::%s (%s, Score: %d)',
                    $plugin->getPluginClass(),
                    $plugin->getInterceptedClass(),
                    $plugin->getInterceptedMethod(),
                    $plugin->getPluginType(),
                    $plugin->getScore()
                );
            }
        }

        foreach ($report->getModules() as $module) {
            if ($module->getScore() >= 7) {
                $issues[] = sprintf(
                    '[MODULE] %s appears unused (Score: %d) — %s',
                    $module->getName(),
                    $module->getScore(),
                    $module->getRecommendation()
                );
            }
        }

        return $issues;
    }
}
