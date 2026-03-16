<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Api\Data;

/**
 * Data contract for a single plugin (interceptor) audit result.
 */
interface PluginDataInterface
{
    /**
     * Get module name defining this plugin.
     */
    public function getModuleName(): string;

    /**
     * Set module name.
     */
    public function setModuleName(string $name): void;

    /**
     * Get intercepted class (fully qualified).
     */
    public function getInterceptedClass(): string;

    /**
     * Set intercepted class.
     */
    public function setInterceptedClass(string $class): void;

    /**
     * Get intercepted method name.
     */
    public function getInterceptedMethod(): string;

    /**
     * Set intercepted method.
     */
    public function setInterceptedMethod(string $method): void;

    /**
     * Get plugin class name.
     */
    public function getPluginClass(): string;

    /**
     * Set plugin class.
     */
    public function setPluginClass(string $class): void;

    /**
     * Get plugin type ('before', 'after', 'around').
     */
    public function getPluginType(): string;

    /**
     * Set plugin type.
     */
    public function setPluginType(string $type): void;

    /**
     * Get plugin sort order.
     */
    public function getSortOrder(): int;

    /**
     * Set sort order.
     */
    public function setSortOrder(int $order): void;

    /**
     * Check if plugin is disabled in di.xml.
     */
    public function isDisabled(): bool;

    /**
     * Set disabled flag.
     */
    public function setDisabled(bool $disabled): void;

    /**
     * Get depth in the plugin chain (how many plugins intercept same method).
     */
    public function getChainDepth(): int;

    /**
     * Set chain depth.
     */
    public function setChainDepth(int $depth): void;

    /**
     * Get performance impact score (0–10).
     */
    public function getScore(): int;

    /**
     * Set performance impact score.
     */
    public function setScore(int $score): void;

    /**
     * Check if plugin likely contains heavy business logic (database calls).
     */
    public function likelyHasBusinessLogic(): bool;

    /**
     * Set business logic flag.
     */
    public function setLikelyHasBusinessLogic(bool $has): void;
}
