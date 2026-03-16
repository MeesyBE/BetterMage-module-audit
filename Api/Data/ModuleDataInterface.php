<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Api\Data;

/**
 * Data contract for a single module audit result.
 */
interface ModuleDataInterface
{
    /**
     * Get module name (e.g., 'Magento_Catalog').
     */
    public function getName(): string;

    /**
     * Set module name.
     */
    public function setName(string $name): void;

    /**
     * Get module version.
     */
    public function getVersion(): string;

    /**
     * Set module version.
     */
    public function setVersion(string $version): void;

    /**
     * Check if module is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Set enabled status.
     */
    public function setEnabled(bool $enabled): void;

    /**
     * Get performance impact score (0–10).
     */
    public function getScore(): int;

    /**
     * Set performance impact score.
     */
    public function setScore(int $score): void;

    /**
     * Get scoring reason/explanation.
     */
    public function getScoreReason(): string;

    /**
     * Set scoring reason.
     */
    public function setScoreReason(string $reason): void;

    /**
     * Check if module has routes (frontend or admin).
     */
    public function hasRoutes(): bool;

    /**
     * Set routes flag.
     */
    public function setHasRoutes(bool $has): void;

    /**
     * Check if module registers observers.
     */
    public function hasObservers(): bool;

    /**
     * Set observers flag.
     */
    public function setHasObservers(bool $has): void;

    /**
     * Check if module registers plugins.
     */
    public function hasPlugins(): bool;

    /**
     * Set plugins flag.
     */
    public function setHasPlugins(bool $has): void;

    /**
     * Check if module has cron jobs.
     */
    public function hasCron(): bool;

    /**
     * Set cron flag.
     */
    public function setHasCron(bool $has): void;

    /**
     * Check if module has system configuration.
     */
    public function hasConfig(): bool;

    /**
     * Set config flag.
     */
    public function setHasConfig(bool $has): void;

    /**
     * Check if module has database schema/tables.
     */
    public function hasDatabase(): bool;

    /**
     * Set database flag.
     */
    public function setHasDatabase(bool $has): void;

    /**
     * Get modules that depend on this module.
     *
     * @return array<int, string> Module names
     */
    public function getDependents(): array;

    /**
     * Set dependents.
     *
     * @param array<int, string> $dependents
     */
    public function setDependents(array $dependents): void;

    /**
     * Get recommendation text.
     */
    public function getRecommendation(): string;

    /**
     * Set recommendation.
     */
    public function setRecommendation(string $recommendation): void;
}
