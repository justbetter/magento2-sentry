<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Http;

use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;

/**
 * Transparent decorator that remembers the last raw response from Sentry, so callers can inspect
 * headers such as X-Sentry-Rate-Limits even when the SDK itself treats the send as successful.
 */
class TrackingHttpClient implements HttpClientInterface
{
    /**
     * @var ?Response
     */
    private ?Response $lastResponse = null;

    /**
     * TrackingHttpClient constructor.
     *
     * @param HttpClientInterface $httpClient
     */
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(Request $request, Options $options): Response
    {
        return $this->lastResponse = $this->httpClient->sendRequest($request, $options);
    }

    /**
     * Get the raw response of the most recent request sent through this client, if any.
     */
    public function getLastResponse(): ?Response
    {
        return $this->lastResponse;
    }
}
