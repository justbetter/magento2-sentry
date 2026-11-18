<?php

//

namespace JustBetter\Sentry\Logger\Handler;

use ArrayAccess;
use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Monolog\Handler\AbstractHandler;
use Throwable;

class Sentry extends AbstractHandler
{
    /**
     * Construct.
     *
     * @param Data             $sentryHelper
     * @param SentryLog        $sentryLog
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
    public function isHandling(array|ArrayAccess $record): bool
    {
        try {
            return $this->deploymentConfig->isAvailable() && $this->sentryHelper->isActive();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function handle(array|ArrayAccess $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $this->sentryLog->send($record['message'], $record['level'], $record['context']);

        return false;
    }
}
