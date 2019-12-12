<?php

namespace JustBetter\Sentry\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ScriptTagPlacement implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'head.additional', 'label' => __('head.additional')],
            ['value' => 'before.body.end', 'label' => __('before.body.end')],
        ];
    }
}
