<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Transport;

use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\RateLimitState;
use JustBetter\Sentry\Model\Transport\ResponseRecordingHttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;

class ResponseRecordingHttpClientTest extends TestCase
{
    /**
     * @dataProvider statusCodeProvider
     */
    public function testRecordsResponse(int $statusCode, ?string $expectedBreakerCall): void
    {
        $request = new Request();
        $options = new Options();
        $response = new Response($statusCode, [], '');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request, $options)
            ->willReturn($response);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->expects($expectedBreakerCall === 'recordSuccess' ? $this->once() : $this->never())
            ->method('recordSuccess');
        $circuitBreaker->expects($expectedBreakerCall === 'recordFailure' ? $this->once() : $this->never())
            ->method('recordFailure');

        $rateLimitState = $this->createMock(RateLimitState::class);
        $rateLimitState->expects($this->once())->method('record')->with($response);

        $result = (new ResponseRecordingHttpClient($httpClient, $circuitBreaker, $rateLimitState))
            ->sendRequest($request, $options);

        $this->assertSame($response, $result);
    }

    /**
     * @return array<string, array{0: int, 1: string|null}>
     */
    public static function statusCodeProvider(): array
    {
        return [
            'accepted'               => [200, 'recordSuccess'],
            'server error'           => [503, 'recordFailure'],
            'network error status 0' => [0, 'recordFailure'],
            'rate limited'           => [429, null],
            'invalid payload'        => [400, null],
            'content too large'      => [413, null],
        ];
    }

    public function testClientExceptionRecordsFailureAndIsRethrown(): void
    {
        $exception = new RuntimeException('curl init failed');
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException($exception);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->expects($this->once())->method('recordFailure');

        $rateLimitState = $this->createMock(RateLimitState::class);
        $rateLimitState->expects($this->never())->method('record');

        $this->expectExceptionObject($exception);

        (new ResponseRecordingHttpClient($httpClient, $circuitBreaker, $rateLimitState))
            ->sendRequest(new Request(), new Options());
    }
}
