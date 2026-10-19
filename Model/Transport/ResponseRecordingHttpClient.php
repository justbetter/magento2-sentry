<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\RateLimitState;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Sentry\Transport\ResultStatus;
use Throwable;

/**
 * Feeds every Sentry HTTP response into the circuit breaker and the shared rate limits.
 *
 * Sits below \Sentry\Transport\HttpTransport, which never exposes the raw response to callers.
 */
class ResponseRecordingHttpClient implements HttpClientInterface
{
    /**
     * @param HttpClientInterface $httpClient
     * @param CircuitBreaker      $circuitBreaker
     * @param RateLimitState      $rateLimitState
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RateLimitState $rateLimitState
    ) {
    }

    public function sendRequest(Request $request, Options $options): Response
    {
        try {
            $response = $this->httpClient->sendRequest($request, $options);
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailure();

            throw $exception;
        }

        // Sentry also announces limits on accepted envelopes, e.g. when it drops an attached profile.
        $this->rateLimitState->record($response);

        switch (ResultStatus::createFromHttpStatusCode($response->getStatusCode())) {
            case ResultStatus::success():
                $this->circuitBreaker->recordSuccess();
                break;
            case ResultStatus::failed():
            case ResultStatus::unknown():
                $this->circuitBreaker->recordFailure();
                break;
                // rate limit / invalid / content too large: Sentry answered, so it says nothing about its health
        }

        return $response;
    }
}
