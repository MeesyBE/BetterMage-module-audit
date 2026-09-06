<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ExportFormat implements OptionSourceInterface
{
    /**
     * @return list<array{value: string, label: Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'cli', 'label' => __('CLI Table')],
            ['value' => 'json', 'label' => __('JSON')],
            ['value' => 'html', 'label' => __('HTML Report')],
        ];
    }
}
