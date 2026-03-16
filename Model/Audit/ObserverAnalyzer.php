<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Model\Data\ObserverData;
use Magento\Framework\Event\ConfigInterface as EventConfig;

/**
 * Analyzes all registered event observers for performance impact.
 *
 * Detects:
 * - High-frequency events (fired on every request)
 * - Missing/broken observer classes
 * - Observer scope (frontend, adminhtml, global)
 */
class ObserverAnalyzer
{
    /**
     * Events that fire on every request (high performance impact).
     */
    private const HIGH_FREQUENCY_EVENTS = [
        'controller_action_predispatch',
        'controller_action_postdispatch',
        'layout_load_before',
        'layout_generate_blocks_before',
        'layout_generate_blocks_after',
        'controller_front_send_response_before',
        'controller_front_send_response_after',
    ];

    public function __construct(
        private readonly EventConfig $eventConfig,
    ) {
    }

    /**
     * Analyze all registered observers.
     *
     * @return array<int, ObserverDataInterface>
     */
    public function analyze(): array
    {
        $observers = [];
        $eventObservers = $this->eventConfig->getObservers('global');

        foreach ($eventObservers as $eventName => $observerList) {
            if (!is_array($observerList)) {
                continue;
            }

            foreach ($observerList as $observerName => $observerConfig) {
                $observers[] = $this->buildObserverData($eventName, $observerName, $observerConfig);
            }
        }

        return $observers;
    }

    /**
     * Build observer audit data.
     *
     * @param array<string, mixed> $config
     */
    private function buildObserverData(string $eventName, string $observerName, array $config): ObserverDataInterface
    {
        $data = new ObserverData();
        
        $data->setEventName($eventName);
        $data->setObserverClass($config['instance'] ?? $config['class'] ?? '');
        $data->setObserverMethod($config['method'] ?? 'execute');
        
        // Detect high-frequency events
        $isHighFrequency = $this->isHighFrequencyEvent($eventName);
        $data->setHighFrequency($isHighFrequency);
        
        // Validate observer class exists
        $observerClass = $data->getObserverClass();
        $data->setValid($observerClass !== '' && class_exists($observerClass));
        
        // Extract module name from observer class namespace
        $moduleName = $this->extractModuleName($observerClass);
        $data->setModuleName($moduleName);
        
        // Set scope (can be enhanced to detect frontend/adminhtml)
        $data->setScope('global');
        
        // Set async flag (Magento doesn't have native async by default)
        $data->setAsync(false);
        
        // Calculate preliminary score
        $score = $this->calculateObserverScore($data);
        $data->setScore($score);
        
        return $data;
    }

    /**
     * Check if event is high-frequency (fires on every request).
     */
    private function isHighFrequencyEvent(string $eventName): bool
    {
        foreach (self::HIGH_FREQUENCY_EVENTS as $pattern) {
            if (str_contains($eventName, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract module name from observer class namespace.
     * Example: Magento\Catalog\Observer\ProductSaveObserver -> Magento_Catalog
     */
    private function extractModuleName(string $className): string
    {
        if (empty($className)) {
            return 'Unknown';
        }

        // Extract namespace parts
        $parts = explode('\\', $className);
        
        if (count($parts) >= 2) {
            // Vendor_Module format (e.g., Magento_Catalog)
            return $parts[0] . '_' . $parts[1];
        }
        
        return 'Unknown';
    }

    /**
     * Calculate performance impact score for observer (0-10).
     */
    private function calculateObserverScore(ObserverDataInterface $data): int
    {
        $score = 0;
        
        // High-frequency event = high impact
        if ($data->isHighFrequency()) {
            $score += 6;
        }
        
        // Broken/missing observer class = critical
        if (!$data->isValid()) {
            $score += 8;
        }
        
        // Medium frequency events get moderate score
        if (!$data->isHighFrequency() && $data->isValid()) {
            $score += 2;
        }
        
        // Cap score at 10
        return min($score, 10);
    }
}
