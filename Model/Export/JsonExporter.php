<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Export;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Export\ExporterInterface;

/**
 * Export audit reports as JSON.
 *
 * Produces structured JSON output suitable for:
 * - API integration
 * - Automated parsing
 * - CI/CD pipelines
 * - Data analysis tools
 */
class JsonExporter implements ExporterInterface
{
    /**
     * Export audit report as JSON.
     */
    public function export(AuditReportInterface $report): string
    {
        $data = [
            'metadata' => $this->buildMetadata($report),
            'summary' => $this->buildSummary($report),
            'statistics' => $report->getStatistics(),
            'modules' => $this->buildModules($report),
            'observers' => $this->buildObservers($report),
            'plugins' => $this->buildPlugins($report),
            'top_issues' => $this->buildTopIssues($report),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode report as JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function getFileExtension(): string
    {
        return 'json';
    }

    /**
     * Build metadata section.
     *
     * @return array<string, mixed>
     */
    private function buildMetadata(AuditReportInterface $report): array
    {
        return [
            'version' => '1.0',
            'timestamp' => $report->getExecutedAt(),
            'generated_by' => 'BetterMagento Module Audit',
        ];
    }

    /**
     * Build summary section.
     *
     * @return array<string, mixed>
     */
    private function buildSummary(AuditReportInterface $report): array
    {
        return [
            'score' => $report->getScore(),
            'grade' => $report->getGrade(),
            'timestamp' => $report->getExecutedAt(),
        ];
    }

    /**
     * Build modules array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildModules(AuditReportInterface $report): array
    {
        $modules = [];

        foreach ($report->getModules() as $module) {
            $modules[] = [
                'name' => $module->getName(),
                'version' => $module->getVersion(),
                'enabled' => $module->isEnabled(),
                'score' => $module->getScore(),
                'score_reason' => $module->getScoreReason(),
                'features' => [
                    'has_routes' => $module->hasRoutes(),
                    'has_observers' => $module->hasObservers(),
                    'has_plugins' => $module->hasPlugins(),
                    'has_cron' => $module->hasCron(),
                    'has_config' => $module->hasConfig(),
                    'has_database' => $module->hasDatabase(),
                ],
                'dependents' => $module->getDependents(),
                'recommendation' => $module->getRecommendation(),
            ];
        }

        return $modules;
    }

    /**
     * Build observers array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildObservers(AuditReportInterface $report): array
    {
        $observers = [];

        foreach ($report->getObservers() as $observer) {
            $observers[] = [
                'module_name' => $observer->getModuleName(),
                'event_name' => $observer->getEventName(),
                'observer_class' => $observer->getObserverClass(),
                'observer_method' => $observer->getObserverMethod(),
                'valid' => $observer->isValid(),
                'high_frequency' => $observer->isHighFrequency(),
                'score' => $observer->getScore(),
                'scope' => $observer->getScope(),
                'async' => $observer->isAsync(),
            ];
        }

        return $observers;
    }

    /**
     * Build plugins array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPlugins(AuditReportInterface $report): array
    {
        $plugins = [];

        foreach ($report->getPlugins() as $plugin) {
            $plugins[] = [
                'module_name' => $plugin->getModuleName(),
                'intercepted_class' => $plugin->getInterceptedClass(),
                'intercepted_method' => $plugin->getInterceptedMethod(),
                'plugin_class' => $plugin->getPluginClass(),
                'plugin_type' => $plugin->getPluginType(),
                'sort_order' => $plugin->getSortOrder(),
                'disabled' => $plugin->isDisabled(),
                'chain_depth' => $plugin->getChainDepth(),
                'score' => $plugin->getScore(),
                'likely_has_business_logic' => $plugin->likelyHasBusinessLogic(),
            ];
        }

        return $plugins;
    }

    /**
     * Build top issues array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTopIssues(AuditReportInterface $report): array
    {
        $issues = [];

        // High-impact observers
        foreach ($report->getObservers() as $observer) {
            if ($observer->isHighFrequency() && $observer->getScore() >= 6) {
                $issues[] = [
                    'type' => 'observer',
                    'severity' => 'high',
                    'score' => $observer->getScore(),
                    'module' => $observer->getModuleName(),
                    'description' => sprintf(
                        'High-frequency observer on "%s" event',
                        $observer->getEventName()
                    ),
                    'class' => $observer->getObserverClass(),
                ];
            }
        }

        // High-impact plugins
        foreach ($report->getPlugins() as $plugin) {
            if ($plugin->getScore() >= 7) {
                $issues[] = [
                    'type' => 'plugin',
                    'severity' => $plugin->getScore() >= 8 ? 'high' : 'medium',
                    'score' => $plugin->getScore(),
                    'module' => $plugin->getModuleName(),
                    'description' => sprintf(
                        '%s plugin intercepts %s::%s',
                        ucfirst($plugin->getPluginType()),
                        $plugin->getInterceptedClass(),
                        $plugin->getInterceptedMethod()
                    ),
                    'class' => $plugin->getPluginClass(),
                ];
            }
        }

        // Unused modules
        foreach ($report->getModules() as $module) {
            if ($module->getScore() >= 7) {
                $issues[] = [
                    'type' => 'module',
                    'severity' => 'medium',
                    'score' => $module->getScore(),
                    'module' => $module->getName(),
                    'description' => 'Module appears unused or underutilized',
                    'recommendation' => $module->getRecommendation(),
                ];
            }
        }

        // Sort by score descending
        usort($issues, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top 20 issues
        return array_slice($issues, 0, 20);
    }
}
