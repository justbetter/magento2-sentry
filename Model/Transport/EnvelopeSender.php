<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryInteraction;
use RuntimeException;
use Sentry\Client;
use Sentry\HttpClient\HttpClient;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\Transport\ResultStatus;

/**
 * Sends a pre-serialized Sentry envelope over HTTP (used by the async consumer).
 */
class EnvelopeSender
{
    /**
     * @param Data $helper
     */
    public function __construct(
        private readonly Data $helper
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
     * @throws RuntimeException When delivery fails
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

        $httpClient = new HttpClient(SentryInteraction::SDK_IDENTIFIER, Client::SDK_VERSION);
        $response = $httpClient->sendRequest($request, $options);

        if ($response->hasError()) {
            throw new RuntimeException(
                sprintf('Sentry envelope delivery failed: %s', $response->getError())
            );
        }

        $status = ResultStatus::createFromHttpStatusCode($response->getStatusCode());
        if ((string) $status !== (string) ResultStatus::success()) {
            throw new RuntimeException(
                sprintf(
                    'Sentry envelope delivery failed with HTTP %d (%s).',
                    $response->getStatusCode(),
                    (string) $status
                )
            );
        }
    }
}
