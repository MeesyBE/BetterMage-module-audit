<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Data;

use BetterMagento\ModuleAudit\Model\Data\ModuleData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Data\ModuleData
 */
class ModuleDataTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $data = new ModuleData();

        $this->assertSame('', $data->getName());
        $this->assertSame('', $data->getVersion());
        $this->assertFalse($data->isEnabled());
        $this->assertSame(0, $data->getScore());
        $this->assertSame('', $data->getScoreReason());
        $this->assertFalse($data->hasRoutes());
        $this->assertFalse($data->hasObservers());
        $this->assertFalse($data->hasPlugins());
        $this->assertFalse($data->hasCron());
        $this->assertFalse($data->hasConfig());
        $this->assertFalse($data->hasDatabase());
        $this->assertSame([], $data->getDependents());
        $this->assertSame('', $data->getRecommendation());
    }

    public function testSetAndGetName(): void
    {
        $data = new ModuleData();
        $data->setName('Magento_Catalog');
        $this->assertSame('Magento_Catalog', $data->getName());
    }

    public function testSetAndGetVersion(): void
    {
        $data = new ModuleData();
        $data->setVersion('100.4.7');
        $this->assertSame('100.4.7', $data->getVersion());
    }

    public function testSetAndGetEnabled(): void
    {
        $data = new ModuleData();
        $data->setEnabled(true);
        $this->assertTrue($data->isEnabled());
    }

    public function testSetAndGetScore(): void
    {
        $data = new ModuleData();
        $data->setScore(85);
        $this->assertSame(85, $data->getScore());
    }

    public function testSetAndGetScoreReason(): void
    {
        $data = new ModuleData();
        $data->setScoreReason('High observer count');
        $this->assertSame('High observer count', $data->getScoreReason());
    }

    public function testFeatureFlags(): void
    {
        $data = new ModuleData();

        $data->setHasRoutes(true);
        $this->assertTrue($data->hasRoutes());

        $data->setHasObservers(true);
        $this->assertTrue($data->hasObservers());

        $data->setHasPlugins(true);
        $this->assertTrue($data->hasPlugins());

        $data->setHasCron(true);
        $this->assertTrue($data->hasCron());

        $data->setHasConfig(true);
        $this->assertTrue($data->hasConfig());

        $data->setHasDatabase(true);
        $this->assertTrue($data->hasDatabase());
    }

    public function testSetAndGetDependents(): void
    {
        $data = new ModuleData();
        $data->setDependents(['Magento_Sales', 'Magento_Quote']);
        $this->assertSame(['Magento_Sales', 'Magento_Quote'], $data->getDependents());
    }

    public function testSetAndGetRecommendation(): void
    {
        $data = new ModuleData();
        $data->setRecommendation('Consider disabling unused module');
        $this->assertSame('Consider disabling unused module', $data->getRecommendation());
    }
}
