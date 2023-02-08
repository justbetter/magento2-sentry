<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use Throwable;
use function Sentry\captureException;
use function Sentry\init;

class SentryInteraction
{
    /**
     * @param $config
     * @return void
     */
    public function initialize($config): void
    {
        init($config);
    }

    /**
     * @param Throwable $ex
     * @return void
     */
    public function captureException(Throwable $ex): void
    {
        ob_start();
        captureException($ex);
        ob_end_clean();
    }
}
