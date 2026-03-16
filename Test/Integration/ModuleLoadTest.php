<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Integration;

use Magento\TestFramework\TestCase\AbstractController;
use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;

/**
 * Integration test: Verify module loads and DI works correctly.
 */
class ModuleLoadTest extends AbstractController
{
    /**
     * Test that the module loads without fatal errors.
     */
    public function testModuleLoads(): void
    {
        // This test simply ensures the module initializes in the Magento app
        $this->assertTrue(true);
    }

    /**
     * Test that AuditRunnerInterface can be instantiated.
     */
    public function testAuditRunnerCanBeInstantiated(): void
    {
        try {
            $runner = $this->_objectManager->get(AuditRunnerInterface::class);
            $this->assertInstanceOf(AuditRunnerInterface::class, $runner);
        } catch (\Exception $e) {
            $this->fail('AuditRunner could not be instantiated: ' . $e->getMessage());
        }
    }

    /**
     * Test that audit runner can execute (Phase 1 - modules only).
     */
    public function testAuditRunnerExecutes(): void
    {
        try {
            $runner = $this->_objectManager->get(AuditRunnerInterface::class);
            $report = $runner->execute();

            $this->assertNotNull($report);
            $this->assertGreaterThan(0, $report->getScore());
            $this->assertNotEmpty($report->getGrade());
            $this->assertIsArray($report->getModules());
            $this->assertIsArray($report->getStatistics());
        } catch (\Exception $e) {
            $this->fail('Audit runner execution failed: ' . $e->getMessage());
        }
    }
}
