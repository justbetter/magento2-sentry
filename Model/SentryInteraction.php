<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use JustBetter\Sentry\Helper\Data;
use Throwable;

use function Sentry\captureException;
use function Sentry\init;

class SentryInteraction
{
    public function __construct(
        private Data $sentryHelper
    ) {
    }

    public function initialize($config): void
    {
        init($config);
    }

    public function captureException(Throwable $ex): void
    {
        if (!$this->sentryHelper->shouldCaptureException($ex)) {
            return;
        }

        ob_start();

        try {
            captureException($ex);
        } catch (Throwable) {
        }
        ob_end_clean();
    }
}
