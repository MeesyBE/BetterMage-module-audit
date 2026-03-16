<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Config\Source;

use BetterMagento\ModuleAudit\Model\Config\Source\ExportFormat;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BetterMagento\ModuleAudit\Model\Config\Source\ExportFormat
 */
class ExportFormatTest extends TestCase
{
    public function testToOptionArrayReturnsThreeFormats(): void
    {
        $source = new ExportFormat();
        $options = $source->toOptionArray();

        $this->assertCount(3, $options);
    }

    public function testToOptionArrayContainsExpectedValues(): void
    {
        $source = new ExportFormat();
        $values = array_column($source->toOptionArray(), 'value');

        $this->assertContains('cli', $values);
        $this->assertContains('json', $values);
        $this->assertContains('html', $values);
    }

    public function testEachOptionHasValueAndLabel(): void
    {
        $source = new ExportFormat();

        foreach ($source->toOptionArray() as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertNotEmpty($option['value']);
        }
    }
}
