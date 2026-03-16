<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Api\Data;

/**
 * Data contract for module audit report.
 *
 * Contains all scan results: modules, observers, plugins, scoring, recommendations.
 */
interface AuditReportInterface
{
    /**
     * Get overall audit score (0–100).
     */
    public function getScore(): int;

    /**
     * Set overall audit score.
     */
    public function setScore(int $score): void;

    /**
     * Get overall grade (A–F).
     */
    public function getGrade(): string;

    /**
     * Set overall grade.
     */
    public function setGrade(string $grade): void;

    /**
     * Get timestamp when audit was executed.
     */
    public function getExecutedAt(): string;

    /**
     * Set execution timestamp.
     */
    public function setExecutedAt(string $timestampIso8601): void;

    /**
     * Get all modules data (array of ModuleDataInterface).
     *
     * @return array<int, ModuleDataInterface>
     */
    public function getModules(): array;

    /**
     * Set modules data.
     *
     * @param array<int, ModuleDataInterface> $modules
     */
    public function setModules(array $modules): void;

    /**
     * Get all observers data (array of ObserverDataInterface).
     *
     * @return array<int, ObserverDataInterface>
     */
    public function getObservers(): array;

    /**
     * Set observers data.
     *
     * @param array<int, ObserverDataInterface> $observers
     */
    public function setObservers(array $observers): void;

    /**
     * Get all plugins data (array of PluginDataInterface).
     *
     * @return array<int, PluginDataInterface>
     */
    public function getPlugins(): array;

    /**
     * Set plugins data.
     *
     * @param array<int, PluginDataInterface> $plugins
     */
    public function setPlugins(array $plugins): void;

    /**
     * Get summary statistics as key-value array.
     *
     * @return array<string, int|string>
     */
    public function getStatistics(): array;

    /**
     * Set summary statistics.
     *
     * @param array<string, int|string> $stats
     */
    public function setStatistics(array $stats): void;
}
