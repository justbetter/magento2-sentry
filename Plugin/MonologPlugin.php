<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Logger\Handler\Sentry;
use Monolog\Logger;

class MonologPlugin
{
    /**
     * Channel that reports Sentry delivery failures; routing it to Sentry would loop.
     */
    public const EXCLUDED_CHANNEL = 'justbetter_sentry_delivery';

    /**
     * @param Sentry $sentryHandler The sentry handler we will add to all Monolog loggers.
     */
    public function __construct(
        protected Sentry $sentryHandler,
    ) {
    }

    /**
     * Add the Sentry handler to the Monolog logger if it does not already exist.
     *
     * @param Logger                             $subject
     * @param \Monolog\Handler\AbstractHandler[] $handlers
     *
     * @return \Monolog\Handler\AbstractHandler[][]
     */
    public function beforeSetHandlers(
        Logger $subject,
        array $handlers
    ): array {
        if ($subject->getName() !== self::EXCLUDED_CHANNEL && !$this->containsHandler($handlers)) {
            array_unshift($handlers, $this->sentryHandler);
        }

        return [$handlers];
    }

    /**
     * Check if the Sentry handler is already in the list of handlers.
     *
     * @param \Monolog\Handler\AbstractHandler[] $handlers
     *
     * @return bool
     */
    public function containsHandler(array $handlers): bool
    {
        foreach ($handlers as $handler) {
            if ($handler instanceof Sentry) {
                return true;
            }
        }

        return false;
    }
}
