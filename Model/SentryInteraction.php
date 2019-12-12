<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use function Sentry\captureException;
use function Sentry\init;

class SentryInteraction
{
    public function initialize($config)
    {
        init($config);
    }

    public function captureException(\Throwable $ex)
    {
        ob_start();
        captureException($ex);
        ob_end_clean();
    }
}
