<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Audit;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Detects unused module configuration entries.
 *
 * Analyzes:
 * - System configuration defined in system.xml
 * - Default values in config.xml
 * - Whether config paths are actually referenced in module PHP code
 * - Orphaned config paths (defined but never read)
 */
class ConfigUsageChecker
{
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ModuleDirReader $moduleDirReader,
        private readonly File $fileDriver,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Analyze config usage across all modules.
     *
     * @return array{
     *     modules: array<string, array{defined_paths: array<string>, used_paths: array<string>, unused_paths: array<string>, has_system_xml: bool}>,
     *     stats: array{total_paths: int, used_paths: int, unused_paths: int, modules_with_config: int, modules_with_unused: int}
     * }
     */
    public function analyze(): array
    {
        $modules = [];
        $totalPaths = 0;
        $totalUsed = 0;
        $totalUnused = 0;
        $modulesWithConfig = 0;
        $modulesWithUnused = 0;

        foreach ($this->moduleList->getAll() as $moduleName => $moduleData) {
            $modulePath = $moduleData['path'] ?? '';
            if (!$modulePath) {
                continue;
            }

            $hasSystemXml = $this->fileExists($modulePath . '/etc/adminhtml/system.xml');
            $hasConfigXml = $this->fileExists($modulePath . '/etc/config.xml');

            if (!$hasSystemXml && !$hasConfigXml) {
                continue;
            }

            $modulesWithConfig++;

            // Extract all config paths defined in system.xml and config.xml
            $definedPaths = $this->extractDefinedPaths($modulePath);
            if (empty($definedPaths)) {
                continue;
            }

            // Scan PHP files for references to these config paths
            $usedPaths = $this->findUsedPaths($modulePath, $definedPaths);
            $unusedPaths = array_values(array_diff($definedPaths, $usedPaths));

            $modules[$moduleName] = [
                'defined_paths' => $definedPaths,
                'used_paths' => $usedPaths,
                'unused_paths' => $unusedPaths,
                'has_system_xml' => $hasSystemXml,
            ];

            $totalPaths += count($definedPaths);
            $totalUsed += count($usedPaths);
            $totalUnused += count($unusedPaths);

            if (!empty($unusedPaths)) {
                $modulesWithUnused++;
            }
        }

        return [
            'modules' => $modules,
            'stats' => [
                'total_paths' => $totalPaths,
                'used_paths' => $totalUsed,
                'unused_paths' => $totalUnused,
                'modules_with_config' => $modulesWithConfig,
                'modules_with_unused' => $modulesWithUnused,
            ],
        ];
    }

    /**
     * Extract config paths from system.xml and config.xml.
     *
     * @return array<string> Config paths like "section/group/field"
     */
    private function extractDefinedPaths(string $modulePath): array
    {
        $paths = [];

        // Parse system.xml for field definitions
        $systemXml = $modulePath . '/etc/adminhtml/system.xml';
        if ($this->fileExists($systemXml)) {
            $paths = array_merge($paths, $this->parseSystemXml($systemXml));
        }

        // Parse config.xml for default value definitions
        $configXml = $modulePath . '/etc/config.xml';
        if ($this->fileExists($configXml)) {
            $paths = array_merge($paths, $this->parseConfigXml($configXml));
        }

        return array_values(array_unique($paths));
    }

    /**
     * Parse system.xml to extract config paths (section/group/field).
     *
     * @return array<string>
     */
    private function parseSystemXml(string $filePath): array
    {
        $paths = [];

        try {
            $content = $this->fileDriver->fileGetContents($filePath);
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception) {
            return [];
        }

        foreach ($xml->xpath('//section') as $section) {
            $sectionId = (string) ($section['id'] ?? '');
            if (!$sectionId) {
                continue;
            }

            foreach ($section->xpath('.//group') as $group) {
                $groupId = (string) ($group['id'] ?? '');
                if (!$groupId) {
                    continue;
                }

                foreach ($group->xpath('.//field') as $field) {
                    $fieldId = (string) ($field['id'] ?? '');
                    if ($fieldId) {
                        $paths[] = $sectionId . '/' . $groupId . '/' . $fieldId;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Parse config.xml to extract default config paths.
     *
     * @return array<string>
     */
    private function parseConfigXml(string $filePath): array
    {
        $paths = [];

        try {
            $content = $this->fileDriver->fileGetContents($filePath);
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception) {
            return [];
        }

        // Traverse <default><section><group><field> structure
        if (!isset($xml->default)) {
            return [];
        }

        foreach ($xml->default->children() as $section) {
            $sectionName = $section->getName();
            foreach ($section->children() as $group) {
                $groupName = $group->getName();
                foreach ($group->children() as $field) {
                    $fieldName = $field->getName();
                    $paths[] = $sectionName . '/' . $groupName . '/' . $fieldName;
                }
            }
        }

        return $paths;
    }

    /**
     * Scan PHP files in module for references to config paths.
     *
     * @param array<string> $definedPaths
     * @return array<string> Paths that are referenced in code
     */
    private function findUsedPaths(string $modulePath, array $definedPaths): array
    {
        $usedPaths = [];
        $phpFiles = $this->findPhpFiles($modulePath);

        // Also check XML files for config path references (di.xml, etc.)
        $xmlFiles = $this->findXmlFiles($modulePath);

        $allContent = '';
        foreach (array_merge($phpFiles, $xmlFiles) as $file) {
            try {
                $allContent .= $this->fileDriver->fileGetContents($file) . "\n";
            } catch (\Exception) {
                continue;
            }
        }

        foreach ($definedPaths as $path) {
            // Check if the full path or a parent path segment is referenced
            if (str_contains($allContent, $path)) {
                $usedPaths[] = $path;
                continue;
            }

            // Check for partial path references (e.g., just "group/field")
            $segments = explode('/', $path);
            if (count($segments) >= 2) {
                $partialPath = $segments[count($segments) - 2] . '/' . $segments[count($segments) - 1];
                if (str_contains($allContent, $partialPath)) {
                    $usedPaths[] = $path;
                }
            }
        }

        return array_values(array_unique($usedPaths));
    }

    /**
     * Find all PHP files in the module directory (excluding tests).
     *
     * @return array<string>
     */
    private function findPhpFiles(string $modulePath): array
    {
        return $this->findFilesByExtension($modulePath, '.php');
    }

    /**
     * Find all XML files in the module's etc/ directory.
     *
     * @return array<string>
     */
    private function findXmlFiles(string $modulePath): array
    {
        $etcPath = $modulePath . '/etc';
        if (!$this->fileExists($etcPath)) {
            return [];
        }

        return $this->findFilesByExtension($etcPath, '.xml');
    }

    /**
     * Recursively find files by extension, excluding test directories.
     *
     * @return array<string>
     */
    private function findFilesByExtension(string $directory, string $extension): array
    {
        $files = [];

        try {
            if (!$this->fileDriver->isDirectory($directory)) {
                return [];
            }

            $entries = $this->fileDriver->readDirectory($directory);
        } catch (\Exception) {
            return [];
        }

        foreach ($entries as $entry) {
            $basename = basename($entry);

            // Skip test directories
            if (in_array($basename, ['Test', 'Tests', 'test', 'tests'], true)) {
                continue;
            }

            try {
                if ($this->fileDriver->isDirectory($entry)) {
                    $files = array_merge($files, $this->findFilesByExtension($entry, $extension));
                } elseif (str_ends_with($entry, $extension)) {
                    $files[] = $entry;
                }
            } catch (\Exception) {
                continue;
            }
        }

        return $files;
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
