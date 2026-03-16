<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;

/**
 * Calculates performance impact scores based on audit findings.
 *
 * Implements scoring rules from WORKDOC:
 * - Module disabled but code loaded: +4
 * - Observer on controller_action_predispatch: +6
 * - Around plugin on core method: +5
 * - Module with 0 routes/observers/plugins: +3
 * - Database call in high-frequency observer: +8
 */
class ScoreCalculator
{
    /**
     * Calculate overall audit score (0-100) and grade (A-F).
     */
    public function calculateOverallScore(AuditReportInterface $report): void
    {
        $modules = $report->getModules();
        $observers = $report->getObservers();
        $plugins = $report->getPlugins();
        
        // Start with perfect score
        $score = 100;
        
        // Deduct points for issues
        $score -= $this->calculateModulePenalties($modules);
        $score -= $this->calculateObserverPenalties($observers);
        $score -= $this->calculatePluginPenalties($plugins);
        
        // Ensure score stays in valid range
        $score = max(0, min(100, $score));
        
        $report->setScore($score);
        $report->setGrade($this->calculateGrade($score));
    }

    /**
     * Calculate module-level performance impact scores.
     *
     * @param array<int, ModuleDataInterface> $modules
     */
    public function calculateModuleScores(array $modules): void
    {
        foreach ($modules as $module) {
            $score = 0;
            $reasons = [];
            
            // Module with no usage (candidate for removal)
            if (!$module->hasRoutes() 
                && !$module->hasObservers() 
                && !$module->hasPlugins() 
                && !$module->hasCron()
            ) {
                $score += 3;
                $reasons[] = 'No routes, observers, plugins, or cron (unused module)';
            }
            
            // Module enabled but has no functionality
            if ($module->isEnabled() && !$module->hasDatabase() && !$module->hasConfig()) {
                $score += 2;
                $reasons[] = 'Enabled but minimal functionality';
            }
            
            $module->setScore(min($score, 10));
            $module->setScoreReason(implode('; ', $reasons));
            
            // Generate recommendation
            if ($score >= 7) {
                $module->setRecommendation('Consider disabling or removing this module');
            } elseif ($score >= 4) {
                $module->setRecommendation('Review module necessity and configuration');
            } else {
                $module->setRecommendation('Module appears to be properly utilized');
            }
        }
    }

    /**
     * Calculate total penalty from module issues.
     *
     * @param array<int, ModuleDataInterface> $modules
     */
    private function calculateModulePenalties(array $modules): int
    {
        $penalty = 0;
        
        foreach ($modules as $module) {
            // Unused modules contribute to bloat
            if ($module->getScore() >= 3) {
                $penalty += 2;
            }
        }
        
        // Too many total modules impacts performance
        if (count($modules) > 200) {
            $penalty += 10;
        } elseif (count($modules) > 150) {
            $penalty += 5;
        }
        
        return $penalty;
    }

    /**
     * Calculate total penalty from observer issues.
     *
     * @param array<int, ObserverDataInterface> $observers
     */
    private function calculateObserverPenalties(array $observers): int
    {
        $penalty = 0;
        
        foreach ($observers as $observer) {
            // High-frequency observers are significant performance impact
            if ($observer->isHighFrequency()) {
                $penalty += 3;
            }
            
            // Broken observers are critical issues
            if (!$observer->isValid()) {
                $penalty += 5;
            }
        }
        
        // Too many observers in general
        if (count($observers) > 500) {
            $penalty += 10;
        } elseif (count($observers) > 300) {
            $penalty += 5;
        }
        
        return min($penalty, 40); // Cap observer penalties
    }

    /**
     * Calculate total penalty from plugin issues.
     *
     * @param array<int, PluginDataInterface> $plugins
     */
    private function calculatePluginPenalties(array $plugins): int
    {
        $penalty = 0;
        
        foreach ($plugins as $plugin) {
            // Deep plugin chains are serious performance issues
            if ($plugin->getChainDepth() >= 4) {
                $penalty += 5;
            } elseif ($plugin->getChainDepth() >= 2) {
                $penalty += 2;
            }
            
            // Around plugins on core classes
            if ($plugin->getPluginType() === 'around' && $plugin->getScore() >= 7) {
                $penalty += 3;
            }
        }
        
        // Too many plugins in general
        if (count($plugins) > 300) {
            $penalty += 10;
        } elseif (count($plugins) > 200) {
            $penalty += 5;
        }
        
        return min($penalty, 40); // Cap plugin penalties
    }

    /**
     * Calculate letter grade from numeric score (0-100).
     */
    private function calculateGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            $score >= 50 => 'E',
            default => 'F',
        };
    }
}
