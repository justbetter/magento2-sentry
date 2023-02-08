<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Logger\Monolog;
use Monolog\DateTimeImmutable;

class MonologPlugin extends Monolog
{
    /**
     * {@inheritdoc}
     *
     * @param string $name
     * @param Data $sentryHelper
     * @param SentryLog $sentryLog
     * @param DeploymentConfig $deploymentConfig
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        public string $name,
        protected Data $sentryHelper,
        protected SentryLog $sentryLog,
        protected DeploymentConfig $deploymentConfig,
        array $handlers = [],
        array $processors = []
    ) {
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
    public function addRecord(
        int $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool
    {
        if ($this->deploymentConfig->isAvailable() && $this->sentryHelper->isActive()) {
            $this->sentryLog->send($message, $level, $context);
        }

        return parent::addRecord($level, $message, $context, $datetime);
    }
}
