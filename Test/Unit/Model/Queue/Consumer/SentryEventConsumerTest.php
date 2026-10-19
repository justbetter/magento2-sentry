<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Queue\Consumer;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Consumer\SentryEventConsumer;
use JustBetter\Sentry\Model\RateLimitParser;
use JustBetter\Sentry\Model\RateLimitState;
use JustBetter\Sentry\Model\Transport\EnvelopeSender;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\Event;
use Sentry\HttpClient\Response;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;

class SentryEventConsumerTest extends TestCase
{
    private function createConsumer(
        ?EnvelopeSender $envelopeSender = null,
        ?CircuitBreaker $circuitBreaker = null,
        ?Data $helper = null,
        ?LoggerInterface $logger = null,
        ?RateLimitState $rateLimitState = null
    ): SentryEventConsumer {
        return new SentryEventConsumer(
            $envelopeSender ?? $this->createStub(EnvelopeSender::class),
            $circuitBreaker ?? $this->createStub(CircuitBreaker::class),
            $rateLimitState ?? $this->createStub(RateLimitState::class),
            $helper ?? $this->activeHelperStub(),
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    private function activeHelperStub(): Data
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isActive')->willReturn(true);

        return $helper;
    }

    public function testSkipsWhenModuleInactive(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isActive')->willReturn(false);

        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->never())->method('send');

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->expects($this->never())->method('allowRequest');

        $this->createConsumer($envelopeSender, $circuitBreaker, $helper)->process('payload');
    }

    public function testSkipsEmptyPayload(): void
    {
        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->never())->method('send');

        $this->createConsumer($envelopeSender)->process('');
    }

    public function testSkipsWhileCircuitIsOpen(): void
    {
        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->never())->method('send');

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->createConsumer($envelopeSender, $circuitBreaker, null, $logger)->process('envelope-bytes');
    }

    /**
     * @dataProvider payloadItemTypeProvider
     */
    public function testSkipsRateLimitedItemType(string $payload, string $expectedItemType): void
    {
        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->never())->method('send');

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $rateLimitState = $this->createMock(RateLimitState::class);
        $rateLimitState->expects($this->once())->method('isLimited')->with($expectedItemType)->willReturn(true);

        $this->createConsumer($envelopeSender, $circuitBreaker, null, null, $rateLimitState)->process($payload);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function payloadItemTypeProvider(): array
    {
        return [
            'check-in envelope'           => ["{}\n{\"type\":\"check_in\"}\n{}", 'check_in'],
            'event with envelope headers' => [
                "{\"event_id\":\"abc\",\"sent_at\":\"2026-10-05T12:00:00Z\"}\n"
                ."{\"type\":\"event\",\"content_type\":\"application/json\"}\n{}",
                'event',
            ],
            'single line payload'         => ['envelope-bytes', ''],
            'unreadable item header'      => ["{}\nnot-json\n{}", ''],
            'item type is not a string'   => ["{}\n{\"type\":1}\n{}", ''],
        ];
    }

    public function testSuccessfulDeliveryDoesNotLog(): void
    {
        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->once())->method('send')->with('envelope-bytes');

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->createConsumer($envelopeSender, $circuitBreaker, null, $logger)->process('envelope-bytes');
    }

    /**
     * @dataProvider failingDependencyProvider
     */
    public function testFailureIsLoggedAndNeverThrown(string $failingDependency): void
    {
        $exception = new RuntimeException('boom');

        $envelopeSender = $this->createStub(EnvelopeSender::class);
        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $rateLimitState = $this->createStub(RateLimitState::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        match ($failingDependency) {
            'delivery'        => $envelopeSender->method('send')->willThrowException($exception),
            'circuit breaker' => $circuitBreaker->method('allowRequest')->willThrowException($exception),
            'rate limits'     => $rateLimitState->method('isLimited')->willThrowException($exception),
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Sentry envelope delivery failed', ['exception' => $exception]);

        $this->createConsumer($envelopeSender, $circuitBreaker, null, $logger, $rateLimitState)
            ->process('envelope-bytes');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function failingDependencyProvider(): array
    {
        return [
            'delivery'        => ['delivery'],
            'circuit breaker' => ['circuit breaker'],
            'rate limits'     => ['rate limits'],
        ];
    }

    /**
     * Guards the consumer's envelope parsing and RateLimitState's category map against
     * what the SDK actually serializes and what Sentry actually names its categories.
     *
     * @dataProvider sentryCategoryProvider
     */
    public function testRateLimitedCategoryHoldsBackOnlyMatchingSdkEnvelopes(string $sentryCategory, string $limitedType): void
    {
        $serializer = new PayloadSerializer(new Options(['dsn' => 'https://key@sentry.example/1']));
        $envelopes = [
            'event'       => $serializer->serialize(Event::createEvent()),
            'transaction' => $serializer->serialize(Event::createTransaction()),
            'check_in'    => $serializer->serialize(Event::createCheckIn()),
            'log'         => $serializer->serialize(
                Event::createLogs()->setLogs([new Log(microtime(true), str_repeat('a', 32), LogLevel::info(), 'msg')])
            ),
        ];

        $storage = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(static function (string $key) use (&$storage): string|false {
            return $storage[$key] ?? false;
        });
        $cache->method('save')->willReturnCallback(static function (string $data, string $key) use (&$storage): bool {
            $storage[$key] = $data;

            return true;
        });
        $rateLimitState = new RateLimitState($cache, new Json(), new RateLimitParser(), $this->createStub(Data::class));
        $rateLimitState->record(new Response(429, ['X-Sentry-Rate-Limits' => ["60:$sentryCategory:organization"]], ''));

        $sent = [];
        $envelopeSender = $this->createStub(EnvelopeSender::class);
        $envelopeSender->method('send')->willReturnCallback(static function (string $payload) use (&$sent): void {
            $sent[] = $payload;
        });

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $consumer = $this->createConsumer($envelopeSender, $circuitBreaker, null, null, $rateLimitState);
        foreach ($envelopes as $payload) {
            $consumer->process($payload);
        }

        $expected = $envelopes;
        unset($expected[$limitedType]);
        $this->assertSame(array_values($expected), $sent);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sentryCategoryProvider(): array
    {
        return [
            'errors'         => ['error', 'event'],
            'transactions'   => ['transaction', 'transaction'],
            'cron check-ins' => ['monitor', 'check_in'],
            'logs'           => ['log_item', 'log'],
        ];
    }
}
