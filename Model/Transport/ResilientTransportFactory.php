<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use JustBetter\Sentry\Model\RateLimitState;
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
     * @param RateLimitState       $rateLimitState
     * @param Data                 $helper
     */
    public function __construct(
        private readonly SentryEventPublisher $publisher,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RateLimitState $rateLimitState,
        private readonly Data $helper
    ) {
    }

    /**
     * Create transport for the given Sentry client options.
     *
     * Request-path client only; the async consumer's client is wired to EnvelopeSender in di.xml.
     *
     * @param Options             $options
     * @param HttpClientInterface $httpClient
     *
     * @return TransportInterface
     */
    public function create(Options $options, HttpClientInterface $httpClient): TransportInterface
    {
        $payloadSerializer = new PayloadSerializer($options);
        $httpTransport = new HttpTransport(
            $options,
            new ResponseRecordingHttpClient($httpClient, $this->circuitBreaker, $this->rateLimitState),
            $payloadSerializer,
            new NullLogger()
        );

        return new ResilientTransport(
            $httpTransport,
            $payloadSerializer,
            $this->publisher,
            $this->circuitBreaker,
            $this->rateLimitState,
            $this->helper
        );
    }
}
