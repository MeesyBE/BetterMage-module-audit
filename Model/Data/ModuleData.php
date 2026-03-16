<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Data;

use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;

/**
 * Concrete implementation of ModuleDataInterface.
 */
class ModuleData implements ModuleDataInterface
{
    private string $name = '';
    private string $version = '';
    private bool $enabled = false;
    private int $score = 0;
    private string $scoreReason = '';
    private bool $hasRoutes = false;
    private bool $hasObservers = false;
    private bool $hasPlugins = false;
    private bool $hasCron = false;
    private bool $hasConfig = false;
    private bool $hasDatabase = false;
    /** @var array<int, string> */
    private array $dependents = [];
    private string $recommendation = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function getScoreReason(): string
    {
        return $this->scoreReason;
    }

    public function setScoreReason(string $reason): void
    {
        $this->scoreReason = $reason;
    }

    public function hasRoutes(): bool
    {
        return $this->hasRoutes;
    }

    public function setHasRoutes(bool $has): void
    {
        $this->hasRoutes = $has;
    }

    public function hasObservers(): bool
    {
        return $this->hasObservers;
    }

    public function setHasObservers(bool $has): void
    {
        $this->hasObservers = $has;
    }

    public function hasPlugins(): bool
    {
        return $this->hasPlugins;
    }

    public function setHasPlugins(bool $has): void
    {
        $this->hasPlugins = $has;
    }

    public function hasCron(): bool
    {
        return $this->hasCron;
    }

    public function setHasCron(bool $has): void
    {
        $this->hasCron = $has;
    }

    public function hasConfig(): bool
    {
        return $this->hasConfig;
    }

    public function setHasConfig(bool $has): void
    {
        $this->hasConfig = $has;
    }

    public function hasDatabase(): bool
    {
        return $this->hasDatabase;
    }

    public function setHasDatabase(bool $has): void
    {
        $this->hasDatabase = $has;
    }

    public function getDependents(): array
    {
        return $this->dependents;
    }

    public function setDependents(array $dependents): void
    {
        $this->dependents = $dependents;
    }

    public function getRecommendation(): string
    {
        return $this->recommendation;
    }

    public function setRecommendation(string $recommendation): void
    {
        $this->recommendation = $recommendation;
    }
}
