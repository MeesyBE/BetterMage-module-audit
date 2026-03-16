<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Test\Unit\Model\Audit;

use BetterMagento\ModuleAudit\Model\Audit\ObserverAnalyzer;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ObserverAnalyzer.
 *
 * Tests event parsing, frequency detection, and observer scoring.
 */
class ObserverAnalyzerTest extends TestCase
{
    private ObserverAnalyzer $analyzer;
    private EventConfig $eventConfig;

    protected function setUp(): void
    {
        $this->eventConfig = $this->createMock(EventConfig::class);
        $this->analyzer = new ObserverAnalyzer($this->eventConfig);
    }

    public function testAnalyzeReturnsEmptyArrayWhenNoObservers(): void
    {
        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn([]);

        $result = $this->analyzer->analyze();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAnalyzeProcessesObserversCorrectly(): void
    {
        $observerData = [
            'controller_action_predispatch' => [
                'test_observer' => [
                    'instance' => 'Vendor\\Module\\Observer\\TestObserver',
                    'method' => 'execute'
                ]
            ],
            'catalog_product_save_after' => [
                'product_observer' => [
                    'instance' => 'Vendor\\Catalog\\Observer\\ProductObserver',
                    'method' => 'execute'
                ]
            ]
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf('BetterMagento\\ModuleAudit\\Api\\Data\\ObserverDataInterface', $result);
    }

    public function testHighFrequencyEventDetection(): void
    {
        $observerData = [
            'controller_action_predispatch' => [
                'high_freq_observer' => [
                    'instance' => 'Vendor\\Module\\Observer\\HighFreqObserver',
                ]
            ],
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->isHighFrequency());
    }

    public function testLowFrequencyEventDetection(): void
    {
        $observerData = [
            'catalog_product_save_after' => [
                'low_freq_observer' => [
                    'instance' => 'Vendor\\Catalog\\Observer\\ProductObserver',
                ]
            ],
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]->isHighFrequency());
    }

    public function testObserverValidation(): void
    {
        $observerData = [
            'test_event' => [
                'valid_observer' => [
                    'instance' => ObserverAnalyzer::class, // Use actual existing class
                ],
                'invalid_observer' => [
                    'instance' => 'NonExistent\\Observer\\Class',
                ]
            ],
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(2, $result);
        
        // First observer (valid)
        $this->assertTrue($result[0]->isValid());
        
        // Second observer (invalid)
        $this->assertFalse($result[1]->isValid());
    }

    public function testModuleNameExtraction(): void
    {
        $observerData = [
            'test_event' => [
                'test_observer' => [
                    'instance' => 'Magento\\Catalog\\Observer\\ProductObserver',
                ]
            ],
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertEquals('Magento_Catalog', $result[0]->getModuleName());
    }

    public function testScoreCalculationForHighFrequencyObserver(): void
    {
        $observerData = [
            'controller_action_predispatch' => [
                'test_observer' => [
                    'instance' => ObserverAnalyzer::class, // Existing valid class
                ]
            ],
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        // High frequency (6) + valid (0) = 6
        $this->assertEquals(6, $result[0]->getScore());
    }

    public function testScoreCalculationForInvalidObserver(): void
    {
        $observerData = [
            'some_event' => [
                'broken_observer' => [
                    'instance' => 'NonExistent\\Class',
                ]
            ],
        ];

        $this->eventConfig
            ->expects($this->once())
            ->method('getObservers')
            ->with('global')
            ->willReturn($observerData);

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        // Invalid observer = 8 points
        $this->assertEquals(8, $result[0]->getScore());
    }
}
