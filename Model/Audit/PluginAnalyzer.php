<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Model\Data\PluginData;
use Magento\Framework\Interception\ConfigInterface as InterceptionConfig;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

/**
 * Analyzes all registered plugins (interceptors) for performance impact.
 *
 * Detects:
 * - Plugin chain depth (multiple plugins on same method)
 * - Plugin type (before, after, around)
 * - Disabled plugins
 * - Core class interception
 */
class PluginAnalyzer
{
    /**
     * Core Magento classes that are frequently called.
     */
    private const CORE_CLASSES = [
        'Magento\Catalog\Model\Product',
        'Magento\Sales\Model\Order',
        'Magento\Customer\Model\Customer',
        'Magento\Quote\Model\Quote',
        'Magento\Checkout\Model\Cart',
        'Magento\Catalog\Model\ResourceModel\Product',
        'Magento\Catalog\Model\ResourceModel\Product\Collection',
        'Magento\Framework\App\Action\Action',
        'Magento\Framework\View\Element\AbstractBlock',
        'Magento\Checkout\Model\Session',
    ];

    /**
     * Scopes to scan for plugin definitions in di.xml.
     */
    private const DI_SCOPES = ['global', 'frontend', 'adminhtml'];

    public function __construct(
        private readonly InterceptionConfig $interceptionConfig,
        private readonly ModuleDirReader $moduleDirReader,
        private readonly FileDriver $fileDriver,
    ) {
    }

    /**
     * Analyze all registered plugins by parsing di.xml files across all modules.
     *
     * @return array<int, PluginDataInterface>
     */
    public function analyze(): array
    {
        $plugins = [];
        $pluginDefinitions = $this->collectPluginDefinitions();

        foreach ($pluginDefinitions as $interceptedClass => $classPlugins) {
            foreach ($classPlugins as $pluginName => $pluginConfig) {
                $pluginData = $this->buildPluginData($interceptedClass, $pluginName, $pluginConfig);
                $plugins[] = $pluginData;
            }
        }

        // Calculate chain depths after collecting all plugins
        $this->updateChainDepths($plugins);

        // Recalculate scores with chain depth info
        foreach ($plugins as $plugin) {
            $isCoreClass = $this->isCoreClass($plugin->getInterceptedClass());
            $plugin->setScore($this->calculatePluginScore($plugin, $isCoreClass));
        }

        return $plugins;
    }

    /**
     * Collect plugin definitions from all module di.xml files.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function collectPluginDefinitions(): array
    {
        $pluginDefinitions = [];
        $moduleDirs = $this->moduleDirReader->getModuleConfigDir();

        foreach ($moduleDirs as $moduleName => $configDir) {
            foreach (self::DI_SCOPES as $scope) {
                $diPath = $scope === 'global'
                    ? $configDir . '/di.xml'
                    : $configDir . '/' . $scope . '/di.xml';

                if (!$this->fileDriver->isExists($diPath)) {
                    continue;
                }

                $scopePlugins = $this->parseDiXmlForPlugins($diPath, $moduleName);

                foreach ($scopePlugins as $interceptedClass => $classPlugins) {
                    foreach ($classPlugins as $pluginName => $pluginConfig) {
                        $pluginConfig['scope'] = $scope;
                        $pluginConfig['source_module'] = $moduleName;
                        $pluginDefinitions[$interceptedClass][$pluginName] = $pluginConfig;
                    }
                }
            }
        }

        return $pluginDefinitions;
    }

    /**
     * Parse a single di.xml file and extract plugin definitions.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function parseDiXmlForPlugins(string $diPath, string $moduleName): array
    {
        $pluginDefinitions = [];

        $content = $this->fileDriver->fileGetContents($diPath);
        if (empty($content)) {
            return $pluginDefinitions;
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_use_internal_errors($previousUseErrors);

        if ($xml === false) {
            return $pluginDefinitions;
        }

        foreach ($xml->type as $typeNode) {
            $typeName = (string)($typeNode['name'] ?? '');
            if (empty($typeName)) {
                continue;
            }

            foreach ($typeNode->plugin as $pluginNode) {
                $pluginName = (string)($pluginNode['name'] ?? '');
                if (empty($pluginName)) {
                    continue;
                }

                $pluginDefinitions[$typeName][$pluginName] = [
                    'instance' => (string)($pluginNode['type'] ?? ''),
                    'sortOrder' => (int)($pluginNode['sortOrder'] ?? 100),
                    'disabled' => strtolower((string)($pluginNode['disabled'] ?? 'false')) === 'true',
                    'source_module' => $moduleName,
                ];
            }
        }

        return $pluginDefinitions;
    }

    /**
     * Update chain depths for all plugins sharing the same intercepted class.
     *
     * @param array<int, PluginDataInterface> $plugins
     */
    private function updateChainDepths(array $plugins): void
    {
        // Group plugins by intercepted class
        $classGroups = [];
        foreach ($plugins as $plugin) {
            $classGroups[$plugin->getInterceptedClass()][] = $plugin;
        }

        foreach ($classGroups as $groupedPlugins) {
            $activeCount = 0;
            foreach ($groupedPlugins as $plugin) {
                if (!$plugin->isDisabled()) {
                    $activeCount++;
                }
            }

            foreach ($groupedPlugins as $plugin) {
                $plugin->setChainDepth($activeCount);
            }
        }
    }

    /**
     * Check if a class is a core Magento class.
     */
    private function isCoreClass(string $className): bool
    {
        foreach (self::CORE_CLASSES as $coreClass) {
            if ($className === $coreClass || str_starts_with($className, $coreClass . '\\')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build plugin audit data.
     *
     * @param array<string, mixed> $pluginConfig
     */
    private function buildPluginData(
        string $interceptedClass,
        string $pluginName,
        array $pluginConfig
    ): PluginDataInterface {
        $data = new PluginData();
        
        $data->setInterceptedClass($interceptedClass);
        $data->setPluginClass($pluginConfig['instance'] ?? '');
        $data->setPluginType($this->detectPluginType($pluginConfig));
        $data->setSortOrder((int)($pluginConfig['sortOrder'] ?? 100));
        $data->setDisabled((bool)($pluginConfig['disabled'] ?? false));
        
        // Extract module name from source module or plugin class namespace
        $moduleName = $pluginConfig['source_module'] ?? $this->extractModuleName($data->getPluginClass());
        $data->setModuleName($moduleName);

        // Detect intercepted methods from the plugin class
        $interceptedMethod = $this->detectInterceptedMethod($data->getPluginClass());
        $data->setInterceptedMethod($interceptedMethod);

        // Detect if plugin likely performs heavy operations
        $data->setLikelyHasBusinessLogic($this->detectBusinessLogic($data->getPluginClass()));

        // Initial score (will be recalculated after chain depth update)
        $isCoreClass = $this->isCoreClass($interceptedClass);
        $score = $this->calculatePluginScore($data, $isCoreClass);
        $data->setScore($score);
        
        return $data;
    }

    /**
     * Detect the primary intercepted method from the plugin class.
     */
    private function detectInterceptedMethod(string $pluginClass): string
    {
        if (empty($pluginClass) || !class_exists($pluginClass)) {
            return '';
        }

        $methods = get_class_methods($pluginClass);
        if (!is_array($methods)) {
            return '';
        }

        foreach ($methods as $method) {
            foreach (['around', 'before', 'after'] as $prefix) {
                if (str_starts_with($method, $prefix)) {
                    return lcfirst(substr($method, strlen($prefix)));
                }
            }
        }

        return '';
    }

    /**
     * Detect if a plugin class likely contains heavy business logic.
     *
     * Inspects the source code for database calls, API calls, and collection loads.
     */
    private function detectBusinessLogic(string $pluginClass): bool
    {
        if (empty($pluginClass) || !class_exists($pluginClass)) {
            return false;
        }

        try {
            $reflection = new \ReflectionClass($pluginClass);
            $fileName = $reflection->getFileName();
            if ($fileName === false || !$this->fileDriver->isExists($fileName)) {
                return false;
            }

            $source = $this->fileDriver->fileGetContents($fileName);
            $indicators = [
                '->getConnection()',
                '->fetchAll(',
                '->fetchRow(',
                '->query(',
                '->load(',
                '->getCollection()',
                'curl_',
                'file_get_contents(',
                '->addFieldToFilter(',
            ];

            foreach ($indicators as $indicator) {
                if (str_contains($source, $indicator)) {
                    return true;
                }
            }
        } catch (\ReflectionException) {
            // Class cannot be reflected, skip
        }

        return false;
    }

    /**
     * Detect plugin type from configuration.
     *
     * @param array<string, mixed> $config
     */
    private function detectPluginType(array $config): string
    {
        // Check which methods are defined in the plugin class
        $pluginClass = $config['instance'] ?? '';
        
        if (empty($pluginClass) || !class_exists($pluginClass)) {
            return 'unknown';
        }
        
        $methods = get_class_methods($pluginClass);
        
        if (is_array($methods)) {
            foreach ($methods as $method) {
                if (str_starts_with($method, 'around')) {
                    return 'around';
                }
            }
            
            foreach ($methods as $method) {
                if (str_starts_with($method, 'before')) {
                    return 'before';
                }
            }
            
            foreach ($methods as $method) {
                if (str_starts_with($method, 'after')) {
                    return 'after';
                }
            }
        }
        
        return 'unknown';
    }

    /**
     * Extract module name from plugin class namespace.
     */
    private function extractModuleName(string $className): string
    {
        if (empty($className)) {
            return 'Unknown';
        }

        $parts = explode('\\', $className);
        
        if (count($parts) >= 2) {
            return $parts[0] . '_' . $parts[1];
        }
        
        return 'Unknown';
    }

    /**
     * Calculate performance impact score for plugin (0-10).
     */
    private function calculatePluginScore(PluginDataInterface $data, bool $isCoreClass): int
    {
        $score = 0;
        
        // Around plugins have highest impact
        if ($data->getPluginType() === 'around') {
            $score += 5;
        } elseif ($data->getPluginType() === 'before') {
            $score += 2;
        } elseif ($data->getPluginType() === 'after') {
            $score += 1;
        }
        
        // Intercepting core classes is higher risk
        if ($isCoreClass) {
            $score += 3;
        }
        
        // High chain depth is problematic
        if ($data->getChainDepth() >= 4) {
            $score += 4;
        } elseif ($data->getChainDepth() >= 2) {
            $score += 2;
        }
        
        // Disabled plugins don't impact runtime
        if ($data->isDisabled()) {
            $score = 0;
        }
        
        return min($score, 10);
    }

    /**
     * Calculate chain depth for a specific intercepted method.
     *
     * @param array<int, PluginDataInterface> $plugins
     */
    private function calculateChainDepth(string $interceptedClass, string $method, array $plugins): int
    {
        $depth = 0;
        
        foreach ($plugins as $plugin) {
            if ($plugin->getInterceptedClass() === $interceptedClass 
                && $plugin->getInterceptedMethod() === $method
                && !$plugin->isDisabled()
            ) {
                $depth++;
            }
        }
        
        return $depth;
    }
}
