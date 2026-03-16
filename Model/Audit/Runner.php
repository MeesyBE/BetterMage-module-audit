<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Model\Data\AuditReport;

/**
 * Main audit runner: orchestrates all audit checks and generates the report.
 *
 * This is the primary entry point for executing a complete performance audit.
 */
class Runner implements AuditRunnerInterface
{
    public function __construct(
        private readonly ModuleScanner $moduleScanner,
        private readonly ObserverAnalyzer $observerAnalyzer,
        private readonly PluginAnalyzer $pluginAnalyzer,
        private readonly ScoreCalculator $scoreCalculator,
    ) {
    }

    /**
     * Execute the full audit scan (modules, observers, plugins).
     */
    public function execute(): AuditReportInterface
    {
        $report = new AuditReport();
        $report->setExecutedAt(date('c')); // ISO 8601 timestamp

        // Phase 1: Module scan
        $modules = $this->moduleScanner->scan();
        
        // Phase 2: Analyze observers
        $observers = $this->observerAnalyzer->analyze();
        
        // Phase 3: Analyze plugins
        $plugins = $this->pluginAnalyzer->analyze();
        
        // Set data on report
        $report->setModules($modules);
        $report->setObservers($observers);
        $report->setPlugins($plugins);
        
        // Phase 4: Calculate scores
        $this->scoreCalculator->calculateModuleScores($modules);
        $this->scoreCalculator->calculateOverallScore($report);

        // Build statistics
        $stats = [
            'total_modules' => count($modules),
            'enabled_modules' => count(array_filter($modules, fn($m) => $m->isEnabled())),
            'modules_with_routes' => count(array_filter($modules, fn($m) => $m->hasRoutes())),
            'modules_with_observers' => count(array_filter($modules, fn($m) => $m->hasObservers())),
            'modules_with_plugins' => count(array_filter($modules, fn($m) => $m->hasPlugins())),
            'modules_with_cron' => count(array_filter($modules, fn($m) => $m->hasCron())),
            'total_observers' => count($observers),
            'high_frequency_observers' => count(array_filter($observers, fn($o) => $o->isHighFrequency())),
            'invalid_observers' => count(array_filter($observers, fn($o) => !$o->isValid())),
            'total_plugins' => count($plugins),
            'around_plugins' => count(array_filter($plugins, fn($p) => $p->getPluginType() === 'around')),
            'deep_chains' => count(array_filter($plugins, fn($p) => $p->getChainDepth() >= 4)),
            'scan_timestamp' => $report->getExecutedAt(),
        ];
        $report->setStatistics($stats);

        return $report;
    }
}
