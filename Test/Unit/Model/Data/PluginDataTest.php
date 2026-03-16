<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Data;

use BetterMagento\ModuleAudit\Model\Data\PluginData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Data\PluginData
 */
class PluginDataTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $data = new PluginData();

        $this->assertSame('', $data->getModuleName());
        $this->assertSame('', $data->getInterceptedClass());
        $this->assertSame('', $data->getInterceptedMethod());
        $this->assertSame('', $data->getPluginClass());
        $this->assertSame('around', $data->getPluginType());
        $this->assertSame(100, $data->getSortOrder());
        $this->assertFalse($data->isDisabled());
        $this->assertSame(1, $data->getChainDepth());
        $this->assertSame(0, $data->getScore());
        $this->assertFalse($data->likelyHasBusinessLogic());
    }

    public function testSetAndGetModuleName(): void
    {
        $data = new PluginData();
        $data->setModuleName('Magento_Sales');
        $this->assertSame('Magento_Sales', $data->getModuleName());
    }

    public function testSetAndGetInterceptedClass(): void
    {
        $data = new PluginData();
        $data->setInterceptedClass('Magento\\Catalog\\Model\\Product');
        $this->assertSame('Magento\\Catalog\\Model\\Product', $data->getInterceptedClass());
    }

    public function testSetAndGetInterceptedMethod(): void
    {
        $data = new PluginData();
        $data->setInterceptedMethod('save');
        $this->assertSame('save', $data->getInterceptedMethod());
    }

    public function testSetAndGetPluginClass(): void
    {
        $data = new PluginData();
        $data->setPluginClass('Vendor\\Module\\Plugin\\ProductPlugin');
        $this->assertSame('Vendor\\Module\\Plugin\\ProductPlugin', $data->getPluginClass());
    }

    public function testSetAndGetPluginType(): void
    {
        $data = new PluginData();
        $data->setPluginType('before');
        $this->assertSame('before', $data->getPluginType());
    }

    public function testSetAndGetSortOrder(): void
    {
        $data = new PluginData();
        $data->setSortOrder(10);
        $this->assertSame(10, $data->getSortOrder());
    }

    public function testSetAndGetDisabled(): void
    {
        $data = new PluginData();
        $data->setDisabled(true);
        $this->assertTrue($data->isDisabled());
    }

    public function testSetAndGetChainDepth(): void
    {
        $data = new PluginData();
        $data->setChainDepth(5);
        $this->assertSame(5, $data->getChainDepth());
    }

    public function testSetAndGetScore(): void
    {
        $data = new PluginData();
        $data->setScore(60);
        $this->assertSame(60, $data->getScore());
    }

    public function testSetAndGetLikelyHasBusinessLogic(): void
    {
        $data = new PluginData();
        $data->setLikelyHasBusinessLogic(true);
        $this->assertTrue($data->likelyHasBusinessLogic());
    }
}
