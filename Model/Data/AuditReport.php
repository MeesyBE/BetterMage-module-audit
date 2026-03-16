<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Data;

use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;

/**
 * Concrete implementation of AuditReportInterface.
 */
class AuditReport implements AuditReportInterface
{
    private int $score = 0;
    private string $grade = 'F';
    private string $executedAt = '';
    /** @var array<int, ModuleDataInterface> */
    private array $modules = [];
    /** @var array<int, ObserverDataInterface> */
    private array $observers = [];
    /** @var array<int, PluginDataInterface> */
    private array $plugins = [];
    /** @var array<string, int|string> */
    private array $statistics = [];

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function getGrade(): string
    {
        return $this->grade;
    }

    public function setGrade(string $grade): void
    {
        $this->grade = $grade;
    }

    public function getExecutedAt(): string
    {
        return $this->executedAt;
    }

    public function setExecutedAt(string $timestampIso8601): void
    {
        $this->executedAt = $timestampIso8601;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }

    public function getObservers(): array
    {
        return $this->observers;
    }

    public function setObservers(array $observers): void
    {
        $this->observers = $observers;
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function setPlugins(array $plugins): void
    {
        $this->plugins = $plugins;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function setStatistics(array $stats): void
    {
        $this->statistics = $stats;
    }
}
