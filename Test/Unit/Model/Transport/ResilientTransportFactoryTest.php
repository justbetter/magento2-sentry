<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use JustBetter\Sentry\Model\RateLimitState;
use JustBetter\Sentry\Model\Transport\ResilientTransportFactory;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Response;
use Sentry\Options;

class ResilientTransportFactoryTest extends TestCase
{
    public function testSyncResponsesAreRecordedIntoCircuitBreakerAndRateLimits(): void
    {
        $response = new Response(503, [], '');
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())->method('sendRequest')->willReturn($response);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);
        $circuitBreaker->expects($this->once())->method('recordFailure');

        $rateLimitState = $this->createMock(RateLimitState::class);
        $rateLimitState->expects($this->once())->method('record')->with($response);

        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);

        $transport = (new ResilientTransportFactory(
            $this->createStub(SentryEventPublisher::class),
            $circuitBreaker,
            $rateLimitState,
            $helper
        ))->create(new Options(['dsn' => 'https://key@sentry.example/1']), $httpClient);

        $transport->send(Event::createEvent());
    }
}
