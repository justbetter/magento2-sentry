<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Logger\Monolog;
use JustBetter\Sentry\Helper\Data as SenteryHelper;
use Monolog\DateTimeImmutable;

class MonologPlugin extends Monolog
{
    /**
     * {@inheritdoc}
     */
    public function __construct(
        $name,
        protected Data $sentryHelper,
        protected SentryLog $sentryLog,
        protected SenteryHelper $sentryHelper,
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
    ): bool {
        if ($this->deploymentConfig->isAvailable() && $this->sentryHelper->isActive()) {
            $this->sentryLog->send($message, $level, $context);
        }

        return parent::addRecord($level, $message, $context, $datetime);
    }
}
