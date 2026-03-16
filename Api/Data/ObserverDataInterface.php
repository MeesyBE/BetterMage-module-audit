<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Api\Data;

/**
 * Data contract for a single observer audit result.
 */
interface ObserverDataInterface
{
    /**
     * Get module name defining this observer.
     */
    public function getModuleName(): string;

    /**
     * Set module name.
     */
    public function setModuleName(string $name): void;

    /**
     * Get event name being observed.
     */
    public function getEventName(): string;

    /**
     * Set event name.
     */
    public function setEventName(string $event): void;

    /**
     * Get observer class name.
     */
    public function getObserverClass(): string;

    /**
     * Set observer class.
     */
    public function setObserverClass(string $class): void;

    /**
     * Get observer method name.
     */
    public function getObserverMethod(): string;

    /**
     * Set observer method.
     */
    public function setObserverMethod(string $method): void;

    /**
     * Check if observer class exists (is not broken).
     */
    public function isValid(): bool;

    /**
     * Set validity flag.
     */
    public function setValid(bool $valid): void;

    /**
     * Check if event frequency is high (fires on every request).
     */
    public function isHighFrequency(): bool;

    /**
     * Set high frequency flag.
     */
    public function setHighFrequency(bool $high): void;

    /**
     * Get performance impact score (0–10).
     */
    public function getScore(): int;

    /**
     * Set performance impact score.
     */
    public function setScore(int $score): void;

    /**
     * Get event scope (e.g., 'frontend', 'adminhtml', 'crontab').
     */
    public function getScope(): string;

    /**
     * Set event scope.
     */
    public function setScope(string $scope): void;

    /**
     * Check if event is async (uses Magento's async queue).
     */
    public function isAsync(): bool;

    /**
     * Set async flag.
     */
    public function setAsync(bool $async): void;
}
