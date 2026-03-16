<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Data;

use BetterMagento\ModuleAudit\Model\Data\ObserverData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Data\ObserverData
 */
class ObserverDataTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $data = new ObserverData();

        $this->assertSame('', $data->getModuleName());
        $this->assertSame('', $data->getEventName());
        $this->assertSame('', $data->getObserverClass());
        $this->assertSame('', $data->getObserverMethod());
        $this->assertTrue($data->isValid());
        $this->assertFalse($data->isHighFrequency());
        $this->assertSame(0, $data->getScore());
        $this->assertSame('global', $data->getScope());
        $this->assertFalse($data->isAsync());
    }

    public function testSetAndGetModuleName(): void
    {
        $data = new ObserverData();
        $data->setModuleName('Magento_Catalog');
        $this->assertSame('Magento_Catalog', $data->getModuleName());
    }

    public function testSetAndGetEventName(): void
    {
        $data = new ObserverData();
        $data->setEventName('catalog_product_save_after');
        $this->assertSame('catalog_product_save_after', $data->getEventName());
    }

    public function testSetAndGetObserverClass(): void
    {
        $data = new ObserverData();
        $data->setObserverClass('Magento\\Catalog\\Observer\\SaveProduct');
        $this->assertSame('Magento\\Catalog\\Observer\\SaveProduct', $data->getObserverClass());
    }

    public function testSetAndGetObserverMethod(): void
    {
        $data = new ObserverData();
        $data->setObserverMethod('execute');
        $this->assertSame('execute', $data->getObserverMethod());
    }

    public function testSetAndGetValid(): void
    {
        $data = new ObserverData();
        $data->setValid(false);
        $this->assertFalse($data->isValid());
    }

    public function testSetAndGetHighFrequency(): void
    {
        $data = new ObserverData();
        $data->setHighFrequency(true);
        $this->assertTrue($data->isHighFrequency());
    }

    public function testSetAndGetScore(): void
    {
        $data = new ObserverData();
        $data->setScore(75);
        $this->assertSame(75, $data->getScore());
    }

    public function testSetAndGetScope(): void
    {
        $data = new ObserverData();
        $data->setScope('frontend');
        $this->assertSame('frontend', $data->getScope());
    }

    public function testSetAndGetAsync(): void
    {
        $data = new ObserverData();
        $data->setAsync(true);
        $this->assertTrue($data->isAsync());
    }
}
