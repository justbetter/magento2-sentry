<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use Psr\Log\NullLogger;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\TransportInterface;

/**
 * Builds a resilient transport bound to the active Sentry Options instance.
 */
class ResilientTransportFactory
{
    /**
     * @param SentryEventPublisher $publisher
     * @param CircuitBreaker       $circuitBreaker
     * @param Data                 $helper
     */
    public function __construct(
        private readonly SentryEventPublisher $publisher,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly Data $helper
    ) {
    }

    /**
     * Create transport for the given Sentry client options.
     *
     * @param Options             $options
     * @param HttpClientInterface $httpClient
     */
    public function create(Options $options, HttpClientInterface $httpClient): TransportInterface
    {
        $payloadSerializer = new PayloadSerializer($options);
        $httpTransport = new HttpTransport(
            $options,
            $httpClient,
            $payloadSerializer,
            new NullLogger()
        );

        return new ResilientTransport(
            $httpTransport,
            $payloadSerializer,
            $this->publisher,
            $this->circuitBreaker,
            $this->helper
        );
    }
}
