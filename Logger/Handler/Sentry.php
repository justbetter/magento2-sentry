<?php

namespace JustBetter\Sentry\Logger\Handler;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Monolog\LogRecord;

// TODO: Remove once V2 support is dropped.
// phpcs:disable Generic.Classes.DuplicateClassName,PSR2.Classes.ClassDeclaration
if (Logger::API < 3) {
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
} else {
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
        public function isHandling(LogRecord $record): bool
        {
            $config = $this->sentryHelper->collectModuleConfig();
            $this->setLevel($config['log_level']);

            return parent::isHandling($record) && $this->deploymentConfig->isAvailable() && $this->sentryHelper->isActive();
        }

        /**
         * @inheritDoc
         */
        public function handle(LogRecord $record): bool
        {
            if (!$this->isHandling($record)) {
                return false;
            }

            $this->sentryLog->send($record['message'], $record['level'], $record['context']);

            return false;
        }
    }
}
// phpcs:enable Generic.Classes.DuplicateClassName,PSR2.Classes.ClassDeclaration
