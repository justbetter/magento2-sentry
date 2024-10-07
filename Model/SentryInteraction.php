<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use function Sentry\captureException;
use function Sentry\init;

class SentryInteraction
{
    /**
     * Initialize Sentry with passed config.
     *
     * @param array $config Sentry config @see: https://docs.sentry.io/platforms/php/configuration/
     *
     * @return void
     */
    public function initialize($config)
    {
        init($config);
    }

    /**
     * Capture passed exception
     *
     * @param \Throwable $ex
     *
     * @return void
     */
    public function captureException(\Throwable $ex)
    {
        ob_start();
        captureException($ex);
        ob_end_clean();
    }
}
