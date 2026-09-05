<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Integration;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Api\Data\AuditReportInterface;
use BetterMagento\ModuleAudit\Api\Data\ModuleDataInterface;
use BetterMagento\ModuleAudit\Api\Data\ObserverDataInterface;
use BetterMagento\ModuleAudit\Api\Data\PluginDataInterface;
use BetterMagento\ModuleAudit\Api\Export\ExporterInterface;
use BetterMagento\ModuleAudit\Model\Data\AuditReport;
use BetterMagento\ModuleAudit\Model\Data\ModuleData;
use BetterMagento\ModuleAudit\Model\Data\ObserverData;
use BetterMagento\ModuleAudit\Model\Data\PluginData;
use BetterMagento\ModuleAudit\Model\Export\CliExporter;
use BetterMagento\ModuleAudit\Model\Export\HtmlExporter;
use BetterMagento\ModuleAudit\Model\Export\JsonExporter;
use PHPUnit\Framework\TestCase;

/**
 * Standalone integration tests: verify module wiring contracts that can be
 * checked without booting a full Magento app (module.xml, registration,
 * Api/Model/Console autoload, data-contract implementation).
 */
class ModuleLoadTest extends TestCase
{
    private string $moduleRoot;

    protected function setUp(): void
    {
        $this->moduleRoot = dirname(__DIR__, 2);
    }

    public function testModuleXmlDeclaresCorrectName(): void
    {
        $xml = $this->loadModuleXml();
        $this->assertSame('BetterMagento_ModuleAudit', (string) $xml['name']);
        $this->assertNotEmpty((string) $xml['setup_version']);
    }

    public function testModuleXmlSequencesCoreModule(): void
    {
        $moduleNames = [];
        foreach ($this->loadModuleXml()->xpath('./sequence/module') as $seqModule) {
            $moduleNames[] = (string) $seqModule['name'];
        }

        $this->assertContains('BetterMagento_Core', $moduleNames);
    }

    public function testRegistrationRegistersModule(): void
    {
        $registrar = \Magento\Framework\Component\ComponentRegistrar::class;

        if (class_exists($registrar)) {
            // registration.php already ran via composer autoload.files during
            // bootstrap; verify the module path is actually resolvable.
            $path = (new $registrar())->getPath(
                \Magento\Framework\Component\ComponentRegistrar::MODULE,
                'BetterMagento_ModuleAudit'
            );
            $this->assertNotEmpty($path, 'Module must be registered with ComponentRegistrar');
            $this->assertFileExists($path . '/registration.php');
        } else {
            // No Magento framework present: fall back to a structural probe.
            $contents = (string) file_get_contents($this->moduleRoot . '/registration.php');
            $this->assertStringContainsString('ComponentRegistrar::register', $contents);
            $this->assertStringContainsString('BetterMagento_ModuleAudit', $contents);
        }
    }

    public function testApiAndModelSourceClassesAutoload(): void
    {
        $missing = [];
        foreach (['Api', 'Model', 'Console'] as $dir) {
            foreach ($this->phpFilesIn($this->moduleRoot . '/' . $dir) as $file) {
                $fqcn = $this->pathToFqcn($file, $dir);
                if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn)) {
                    $missing[] = $fqcn;
                }
            }
        }

        $this->assertSame([], $missing, 'All Api/Model/Console classes must autoload via composer PSR-4');
    }

    public function testDataContractsAreImplemented(): void
    {
        $this->assertInstanceOf(AuditReportInterface::class, new AuditReport());
        $this->assertInstanceOf(ModuleDataInterface::class, new ModuleData());
        $this->assertInstanceOf(ObserverDataInterface::class, new ObserverData());
        $this->assertInstanceOf(PluginDataInterface::class, new PluginData());

        $this->assertInstanceOf(ExporterInterface::class, new CliExporter());
        $this->assertInstanceOf(ExporterInterface::class, new HtmlExporter());
        $this->assertInstanceOf(ExporterInterface::class, new JsonExporter());
    }

    public function testAuditRunnerInterfaceIsLoadable(): void
    {
        // Contract used by di.xml <preference> and console commands.
        $this->assertTrue(interface_exists(AuditRunnerInterface::class));
        $this->assertTrue(interface_exists('BetterMagento\ModuleAudit\Api\AuditRunnerInterface'));
    }

    public function testStatelessDataRoundTrip(): void
    {
        $data = new AuditReport();
        $data->setScore(85);
        $data->setGrade('B');
        $data->setExecutedAt('2026-01-01T00:00:00+00:00');

        $this->assertSame(85, $data->getScore());
        $this->assertSame('B', $data->getGrade());
        $this->assertSame('2026-01-01T00:00:00+00:00', $data->getExecutedAt());
    }

    private function loadModuleXml(): \SimpleXMLElement
    {
        $path = $this->moduleRoot . '/etc/module.xml';
        $this->assertFileExists($path, 'etc/module.xml must exist for the module to load');

        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml, 'etc/module.xml must be valid XML');

        return $xml;
    }

    /**
     * @param array<int, string> $target
     * @return array<int, string>
     */
    private function phpFilesIn(string $dir, array &$target = []): array
    {
        $files = [];
        if (!is_dir($dir)) {
            return $files;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Map a source file path to its PSR-4 FQCN (BetterMagento\ModuleAudit\ root).
     */
    private function pathToFqcn(string $file, string $relativeDir): string
    {
        $relative = substr($file, strlen($this->moduleRoot) + 1);
        $relative = str_replace('\\\\', '\\', $relative);
        $noExt = substr($relative, 0, -4);
        return 'BetterMagento\\ModuleAudit\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $noExt);
    }
}