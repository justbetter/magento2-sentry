<?php

namespace JustBetter\Sentry\Model\Config\Source;

use Monolog\Logger;
use Magento\Framework\Option\ArrayInterface;

class LogLevel implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => Logger::NOTICE, 'label' => __('Notice')],
            ['value' => Logger::WARNING, 'label' => __('Warning')],
            ['value' => Logger::CRITICAL, 'label' => __('Critical')],
            ['value' => Logger::ALERT, 'label' => __('Alert')],
            ['value' => Logger::ALERT, 'label' => __('Emergency')],
        ];
    }
}
