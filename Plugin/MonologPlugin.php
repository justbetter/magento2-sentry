<?php

namespace JustBetter\Sentry\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use JustBetter\Sentry\Helper\Data;
use Magento\Framework\Logger\Monolog;
use JustBetter\Sentry\Model\SentryLog;

class MonologPlugin extends Monolog
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
    * {@inheritdoc}
    */
    public function __construct($name, Data\Proxy $data, SentryLog\Proxy $sentryLog, array $handlers = [], array $processors = [])
    {
        $this->sentryHelper = $data;
        $this->sentryLog = $sentryLog;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Adds a log record to Sentry
     *
     * @param integer $level The logging level
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        if ($this->sentryHelper->isActive()) {
            $this->sentryLog->send($message, $level, $this, $context);
        }

        return parent::addRecord($level, $message, $context);
    }
}
