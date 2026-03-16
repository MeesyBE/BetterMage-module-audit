<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Data;

use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;

/**
 * Concrete implementation of PluginDataInterface.
 */
class PluginData implements PluginDataInterface
{
    private string $moduleName = '';
    private string $interceptedClass = '';
    private string $interceptedMethod = '';
    private string $pluginClass = '';
    private string $pluginType = 'around';
    private int $sortOrder = 100;
    private bool $disabled = false;
    private int $chainDepth = 1;
    private int $score = 0;
    private bool $likelyHasBusinessLogic = false;

    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    public function setModuleName(string $name): void
    {
        $this->moduleName = $name;
    }

    public function getInterceptedClass(): string
    {
        return $this->interceptedClass;
    }

    public function setInterceptedClass(string $class): void
    {
        $this->interceptedClass = $class;
    }

    public function getInterceptedMethod(): string
    {
        return $this->interceptedMethod;
    }

    public function setInterceptedMethod(string $method): void
    {
        $this->interceptedMethod = $method;
    }

    public function getPluginClass(): string
    {
        return $this->pluginClass;
    }

    public function setPluginClass(string $class): void
    {
        $this->pluginClass = $class;
    }

    public function getPluginType(): string
    {
        return $this->pluginType;
    }

    public function setPluginType(string $type): void
    {
        $this->pluginType = $type;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $order): void
    {
        $this->sortOrder = $order;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    public function getChainDepth(): int
    {
        return $this->chainDepth;
    }

    public function setChainDepth(int $depth): void
    {
        $this->chainDepth = $depth;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function likelyHasBusinessLogic(): bool
    {
        return $this->likelyHasBusinessLogic;
    }

    public function setLikelyHasBusinessLogic(bool $has): void
    {
        $this->likelyHasBusinessLogic = $has;
    }
}
