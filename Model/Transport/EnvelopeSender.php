<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use RuntimeException;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\Transport\ResultStatus;

/**
 * Sends a pre-serialized Sentry envelope over HTTP (used by the async consumer).
 */
class EnvelopeSender
{
    /**
     * @param Data                $helper
     * @param HttpClientInterface $httpClient Records responses into the circuit breaker and rate limits (see di.xml)
     */
    public function __construct(
        private readonly Data $helper,
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * POST a pre-serialized envelope to the configured Sentry DSN.
     *
     * Payload must already include fire-time fields (event "timestamp",
     * envelope "sent_at") — this method does not re-serialize or re-stamp.
     *
     * @param string $payload Envelope body produced at publish/fire time
     *
     * @throws RuntimeException When delivery fails for any reason other than a rate limit
     */
    public function send(string $payload): void
    {
        if ($payload === '') {
            throw new RuntimeException('Sentry envelope payload is empty.');
        }

        $dsn = $this->helper->getDSN();
        if (!is_string($dsn) || $dsn === '') {
            throw new RuntimeException('Sentry DSN is not configured.');
        }

        $options = new Options([
            'dsn'                  => $dsn,
            'http_timeout'         => $this->helper->getHttpTimeout(),
            'http_connect_timeout' => $this->helper->getHttpConnectTimeout(),
        ]);

        $request = new Request();
        $request->setStringBody($payload);

        $response = $this->httpClient->sendRequest($request, $options);
        $status = ResultStatus::createFromHttpStatusCode($response->getStatusCode());

        // Rate limits are expected while quota is exhausted and already held back; logging each one is noise.
        if ($status === ResultStatus::success() || $status === ResultStatus::rateLimit()) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Sentry envelope delivery failed with HTTP %d (%s): %s',
                $response->getStatusCode(),
                $status,
                $response->getError()
            )
        );
    }
}
