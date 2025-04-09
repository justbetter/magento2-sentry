<?php

namespace JustBetter\Sentry\Logger\Handler;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Monolog\Handler\AbstractHandler;

class Sentry extends AbstractHandler
{
    /**
     * Construct.
     *
     * @param Data $sentryHelper
     * @param SentryLog $sentryLog
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        protected Data $sentryHelper,
        protected SentryLog $sentryLog,
        protected DeploymentConfig $deploymentConfig,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function isHandling(array $record): bool
    {
        $config = $this->sentryHelper->collectModuleConfig();
        $this->setLevel($config['log_level']);

        return parent::isHandling($record) && $this->deploymentConfig->isAvailable() && $this->sentryHelper->isActive();
    }

    /**
     * @inheritDoc
     */
    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $this->sentryLog->send($record['message'], $record['level'], $record['context']);

        return false;
    }
}
