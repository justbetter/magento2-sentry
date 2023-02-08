<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Monolog\Logger;

class LogLevel implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
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
