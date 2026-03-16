<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Data;

use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;

/**
 * Concrete implementation of ObserverDataInterface.
 */
class ObserverData implements ObserverDataInterface
{
    private string $moduleName = '';
    private string $eventName = '';
    private string $observerClass = '';
    private string $observerMethod = '';
    private bool $valid = true;
    private bool $highFrequency = false;
    private int $score = 0;
    private string $scope = 'global';
    private bool $async = false;

    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    public function setModuleName(string $name): void
    {
        $this->moduleName = $name;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $event): void
    {
        $this->eventName = $event;
    }

    public function getObserverClass(): string
    {
        return $this->observerClass;
    }

    public function setObserverClass(string $class): void
    {
        $this->observerClass = $class;
    }

    public function getObserverMethod(): string
    {
        return $this->observerMethod;
    }

    public function setObserverMethod(string $method): void
    {
        $this->observerMethod = $method;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function isHighFrequency(): bool
    {
        return $this->highFrequency;
    }

    public function setHighFrequency(bool $high): void
    {
        $this->highFrequency = $high;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function setAsync(bool $async): void
    {
        $this->async = $async;
    }
}
