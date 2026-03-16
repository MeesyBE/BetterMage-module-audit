<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Analyzes registered routes across all modules.
 *
 * Detects:
 * - Modules with frontend/adminhtml/API routes
 * - Duplicate route frontNames
 * - Orphaned routes (controllers missing)
 * - Route count per module
 */
class RouteAnalyzer
{
    /** @var array<string, array<string, mixed>> */
    private array $cachedRoutes = [];

    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ModuleDirReader $moduleDirReader,
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Analyze all registered routes across modules.
     *
     * @return array{
     *     routes: array<int, array{module: string, scope: string, front_name: string, id: string, has_controllers: bool}>,
     *     duplicates: array<string, array<string>>,
     *     orphaned: array<int, array{module: string, scope: string, front_name: string}>,
     *     stats: array{total: int, frontend: int, adminhtml: int, by_module: array<string, int>}
     * }
     */
    public function analyze(): array
    {
        $routes = [];
        $frontNameModules = [];
        $byModule = [];

        foreach ($this->moduleList->getAll() as $moduleName => $moduleData) {
            $modulePath = $moduleData['path'] ?? '';
            if (!$modulePath) {
                continue;
            }

            foreach (['frontend', 'adminhtml'] as $scope) {
                $routeFile = $modulePath . '/etc/' . $scope . '/routes.xml';
                if (!$this->fileExists($routeFile)) {
                    continue;
                }

                $parsed = $this->parseRoutesXml($routeFile, $moduleName, $scope);
                foreach ($parsed as $route) {
                    $routes[] = $route;
                    $frontNameModules[$route['front_name']][] = $moduleName;
                    $byModule[$moduleName] = ($byModule[$moduleName] ?? 0) + 1;
                }
            }
        }

        // Find duplicate frontNames
        $duplicates = array_filter($frontNameModules, fn(array $modules) => count($modules) > 1);

        // Find orphaned routes (route declared but no controllers exist)
        $orphaned = array_filter($routes, fn(array $route) => !$route['has_controllers']);

        // Stats
        $frontendCount = count(array_filter($routes, fn(array $r) => $r['scope'] === 'frontend'));
        $adminhtmlCount = count(array_filter($routes, fn(array $r) => $r['scope'] === 'adminhtml'));

        return [
            'routes' => $routes,
            'duplicates' => $duplicates,
            'orphaned' => $orphaned,
            'stats' => [
                'total' => count($routes),
                'frontend' => $frontendCount,
                'adminhtml' => $adminhtmlCount,
                'by_module' => $byModule,
            ],
        ];
    }

    /**
     * Parse a routes.xml file and extract route definitions.
     *
     * @return array<int, array{module: string, scope: string, front_name: string, id: string, has_controllers: bool}>
     */
    private function parseRoutesXml(string $filePath, string $moduleName, string $scope): array
    {
        $routes = [];

        try {
            $content = $this->fileDriver->fileGetContents($filePath);
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception) {
            return [];
        }

        foreach ($xml->xpath('//route') as $routeNode) {
            $routeId = (string) ($routeNode['id'] ?? '');
            $frontName = (string) ($routeNode['frontName'] ?? '');

            if (empty($routeId) || empty($frontName)) {
                continue;
            }

            $routes[] = [
                'module' => $moduleName,
                'scope' => $scope,
                'front_name' => $frontName,
                'id' => $routeId,
                'has_controllers' => $this->hasControllers($moduleName, $scope),
            ];
        }

        return $routes;
    }

    /**
     * Check if a module has controller classes for the given scope.
     */
    private function hasControllers(string $moduleName, string $scope): bool
    {
        try {
            $moduleDir = $this->moduleDirReader->getModuleDir('', $moduleName);
        } catch (\Exception) {
            return false;
        }

        $controllerDir = $scope === 'adminhtml'
            ? $moduleDir . '/Controller/Adminhtml'
            : $moduleDir . '/Controller';

        if (!$this->fileExists($controllerDir)) {
            return false;
        }

        try {
            $files = $this->fileDriver->readDirectory($controllerDir);
            foreach ($files as $file) {
                if (str_ends_with($file, '.php')) {
                    return true;
                }
                // Check subdirectories
                if ($this->fileDriver->isDirectory($file)) {
                    $subFiles = $this->fileDriver->readDirectory($file);
                    foreach ($subFiles as $subFile) {
                        if (str_ends_with($subFile, '.php')) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Exception) {
            return false;
        }

        return false;
    }

    private function fileExists(string $path): bool
    {
        try {
            return $this->fileDriver->isExists($path);
        } catch (\Exception) {
            return false;
        }
    }
}
