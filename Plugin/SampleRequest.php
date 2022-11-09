<?php

namespace JustBetter\Sentry\Plugin;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;

/**
 * Plugin to sample request and send them to Sentry
 */
class SampleRequest
{
    /** @var SentryPerformance */
    private $sentryPerformance;

    /** @var Http */
    private $request;

    public function __construct(SentryPerformance $sentryPerformance, Http $request)
    {
        $this->sentryPerformance = $sentryPerformance;
        $this->request = $request;
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
