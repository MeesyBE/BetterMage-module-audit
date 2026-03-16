<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ExportFormat implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'cli', 'label' => __('CLI Table')],
            ['value' => 'json', 'label' => __('JSON')],
            ['value' => 'html', 'label' => __('HTML Report')],
        ];
    }
}
