<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\App\ResponseInterface;

/**
 * Plugin to sample request and send them to Sentry.
 */
class SampleRequest
{
    /**
     * SampleRequest constructor.
     *
     * @param SentryPerformance $sentryPerformance
     */
    public function __construct(
        private SentryPerformance $sentryPerformance
    ) {
        $this->sentryPerformance = $sentryPerformance;
    }

    /**
     * Add our toolbar to the response.
     *
     * @param ResponseInterface $response
     */
    public function beforeSendResponse(ResponseInterface $response)
    {
        $this->sentryPerformance->finishTransaction($response);
    }
}
