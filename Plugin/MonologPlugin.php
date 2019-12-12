<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Logger\Monolog;

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
     * @var DeploymentConfig
     */
    protected $deploymentConfig;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        $name,
        Data $data,
        SentryLog $sentryLog,
        DeploymentConfig $deploymentConfig,
        array $handlers = [],
        array $processors = []
    ) {
        $this->sentryHelper = $data;
        $this->sentryLog = $sentryLog;
        $this->deploymentConfig = $deploymentConfig;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Adds a log record to Sentry.
     *
     * @param int    $level   The logging level
     * @param string $message The log message
     * @param array  $context The log context
     *
     * @return bool Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        if ($this->deploymentConfig->isAvailable() && $this->sentryHelper->isActive()) {
            $this->sentryLog->send($message, $level, $this, $context);
        }

        return parent::addRecord($level, $message, $context);
    }
}
