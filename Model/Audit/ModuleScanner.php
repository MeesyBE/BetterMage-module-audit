<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Model\Data\ModuleData;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Filesystem\Driver\File;
use UnexpectedValueException;

/**
 * Scans all Magento modules and detects their features (routes, observers, plugins, cron, config, etc.).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ModuleScanner
{
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Scan all modules and return their audit data.
     *
     * @return array<int, ModuleDataInterface>
     */
    public function scan(): array
    {
        $modules = [];
        $moduleInfo = $this->moduleList->getAll();

        foreach ($moduleInfo as $moduleName => $moduleData) {
            $modules[] = $this->buildModuleData($moduleName, $moduleData);
        }

        return $modules;
    }

    /**
     * Build audit data for a single module.
     *
     * @param array<string, mixed> $moduleData Magento module info
     */
    private function buildModuleData(string $moduleName, array $moduleData): ModuleDataInterface
    {
        $data = new ModuleData();
        $data->setName($moduleName);
        $data->setVersion($moduleData['setup_version'] ?? '0.0.0');
        $data->setEnabled(true); // If it's in moduleList, it's enabled

        // Detect features
        $modulePath = $moduleData['path'] ?? '';
        if ($modulePath) {
            $data->setHasRoutes($this->hasRoutes($modulePath));
            $data->setHasObservers($this->hasObservers($modulePath));
            $data->setHasPlugins($this->hasPlugins($modulePath));
            $data->setHasCron($this->hasCron($modulePath));
            $data->setHasConfig($this->hasConfig($modulePath));
            $data->setHasDatabase($this->hasDatabase($modulePath));
        }

        return $data;
    }

    /**
     * Check if module has frontend or admin routes.
     */
    private function hasRoutes(string $modulePath): bool
    {
        return $this->fileExists($modulePath . '/etc/frontend/routes.xml')
            || $this->fileExists($modulePath . '/etc/adminhtml/routes.xml')
            || $this->fileExists($modulePath . '/etc/routes.xml');
    }

    /**
     * Check if module registers observers (events).
     */
    private function hasObservers(string $modulePath): bool
    {
        return $this->fileExists($modulePath . '/etc/events.xml')
            || $this->fileExists($modulePath . '/etc/frontend/events.xml')
            || $this->fileExists($modulePath . '/etc/adminhtml/events.xml')
            || $this->fileExists($modulePath . '/etc/crontab.xml');
    }

    /**
     * Check if module registers any plugins (interceptors).
     */
    private function hasPlugins(string $modulePath): bool
    {
        $diXmlPath = $modulePath . '/etc/di.xml';
        if (!$this->fileExists($diXmlPath)) {
            return false;
        }

        try {
            $content = $this->fileDriver->fileGetContents($diXmlPath);
            return stripos($content, '<plugin') !== false;
        } catch (UnexpectedValueException) {
            return false;
        }
    }

    /**
     * Check if module has cron jobs.
     */
    private function hasCron(string $modulePath): bool
    {
        return $this->fileExists($modulePath . '/etc/crontab.xml');
    }

    /**
     * Check if module has system configuration.
     */
    private function hasConfig(string $modulePath): bool
    {
        return $this->fileExists($modulePath . '/etc/adminhtml/system.xml')
            || $this->fileExists($modulePath . '/etc/system.xml');
    }

    /**
     * Check if module has database schema.
     */
    private function hasDatabase(string $modulePath): bool
    {
        return $this->fileExists($modulePath . '/etc/db_schema.xml')
            || $this->fileExists($modulePath . '/Setup/InstallSchema.php')
            || $this->fileExists($modulePath . '/Setup/UpgradeSchema.php');
    }

    /**
     * Check if file exists using the file driver.
     */
    private function fileExists(string $filePath): bool
    {
        try {
            return $this->fileDriver->isExists($filePath);
        } catch (UnexpectedValueException) {
            return false;
        }
    }
}
