<?php

namespace JustBetter\Sentry\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use JustBetter\Sentry\Helper\Data;
use Magento\Framework\Logger\Monolog;
use JustBetter\Sentry\Model\SentryLog;

class MonologPlugin
{
    /**
     * @var Data
     */
    protected $sentryHelper;

    /**
     * @var SentryLog
     */
    protected $sentryLog;

    /**
     * @param Data $data
     */
    public function __construct(Data $data, SentryLog $sentryLog)
    {
        $this->sentryHelper = $data;
        $this->sentryLog = $sentryLog;
    }

    /**
     * Before adding monolog record, send it to Sentry
     * This send all magento system logs
     *
     * @param  Monolog  $monolog
     * @param  int      $level
     * @param  string   $message
     * @param  array   $context
     */
    public function beforeAddRecord(Monolog $monolog, $level, $message, array $context = [])
    {
        if ($this->sentryHelper->isActive()) {
            $this->sentryLog->send($message, $level, $monolog, $context);
        }
    }
}
