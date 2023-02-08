<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ScriptTagPlacement implements ArrayInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'head.additional', 'label' => __('head.additional')],
            ['value' => 'before.body.end', 'label' => __('before.body.end')],
        ];
    }
}
