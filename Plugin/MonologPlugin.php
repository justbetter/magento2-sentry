<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Logger\Handler\Sentry;
use JustBetter\Sentry\Model\SentryLog;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Logger\Monolog;
use Monolog\DateTimeImmutable;

class MonologPlugin extends Monolog
{
    /**
     * @psalm-param array<callable(array): array> $processors
     *
     * @param string                              $name             The logging channel, a simple descriptive name that is attached to all log records
     * @param Data                                $sentryHelper
     * @param SentryLog                           $sentryLog
     * @param DeploymentConfig                    $deploymentConfig
     * @param \Monolog\Handler\HandlerInterface[] $handlers         Optional stack of handlers, the first one in the array is called first, etc.
     * @param callable[]                          $processors       Optional array of processors
     */
    public function __construct(
        $name,
        Sentry $sentryHandler,
        array $handlers = [],
        array $processors = []
    ) {
        $handlers['sentry'] = $sentryHandler;

        parent::__construct($name, $handlers, $processors);
    }
}
