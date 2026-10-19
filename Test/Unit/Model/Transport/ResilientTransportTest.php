<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use JustBetter\Sentry\Model\RateLimitState;
use JustBetter\Sentry\Model\Transport\ResilientTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

class ResilientTransportTest extends TestCase
{
    private function createTransport(
        TransportInterface $httpTransport,
        PayloadSerializerInterface $payloadSerializer,
        SentryEventPublisher $publisher,
        CircuitBreaker $circuitBreaker,
        Data $helper,
        ?RateLimitState $rateLimitState = null
    ): ResilientTransport {
        return new ResilientTransport(
            $httpTransport,
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $rateLimitState ?? $this->createStub(RateLimitState::class),
            $helper
        );
    }

    public function testAsyncEnabledQueuesWithoutHttp(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(true);

        $payloadSerializer = $this->createStub(PayloadSerializerInterface::class);
        $payloadSerializer->method('serialize')->willReturn("envelope\nbody");

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->once())->method('publish')->with("envelope\nbody");

        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport->expects($this->never())->method('send');

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $result = $this->createTransport(
            $httpTransport,
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::success(), (string) $result->getStatus());
        $this->assertSame($event, $result->getEvent());
    }

    /**
     * @dataProvider httpResultStatusProvider
     */
    public function testSyncReturnsHttpTransportResultWithoutTouchingCircuit(ResultStatus $status): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);

        // Responses are recorded by ResponseRecordingHttpClient below the HTTP transport.
        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);
        $circuitBreaker->expects($this->never())->method('recordSuccess');
        $circuitBreaker->expects($this->never())->method('recordFailure');

        $httpResult = new Result($status, $event);
        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport->expects($this->once())->method('send')->with($event)->willReturn($httpResult);

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame($httpResult, $result);
    }

    /**
     * @return array<string, array{0: ResultStatus}>
     */
    public static function httpResultStatusProvider(): array
    {
        return [
            'success'    => [ResultStatus::success()],
            'failed'     => [ResultStatus::failed()],
            'invalid'    => [ResultStatus::invalid()],
            'rate limit' => [ResultStatus::rateLimit()],
        ];
    }

    public function testSyncHttpExceptionReturnsFailed(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $httpTransport = $this->createStub(TransportInterface::class);
        $httpTransport->method('send')->willThrowException(new RuntimeException('serializer failed'));

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $this->createStub(SentryEventPublisher::class),
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::failed(), (string) $result->getStatus());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function deliveryModeProvider(): array
    {
        return [
            'sync'  => [false],
            'async' => [true],
        ];
    }

    /**
     * @dataProvider deliveryModeProvider
     */
    public function testOpenCircuitDropsEventWithoutDelivering(bool $async): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn($async);

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(false);

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport->expects($this->never())->method('send');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper
        )->send(Event::createEvent());

        $this->assertSame((string) ResultStatus::failed(), (string) $result->getStatus());
    }

    /**
     * @dataProvider deliveryModeProvider
     */
    public function testRateLimitedCategoryIsDroppedWithoutDelivering(bool $async): void
    {
        $event = Event::createCheckIn();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn($async);

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $rateLimitState = $this->createMock(RateLimitState::class);
        $rateLimitState->expects($this->once())->method('isLimited')->with('check_in')->willReturn(true);

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport->expects($this->never())->method('send');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper,
            $rateLimitState
        )->send($event);

        $this->assertSame((string) ResultStatus::rateLimit(), (string) $result->getStatus());
        $this->assertSame($event, $result->getEvent());
    }

    /**
     * @dataProvider deliveryModeProvider
     */
    public function testUnlimitedCategoryIsDelivered(bool $async): void
    {
        $event = Event::createTransaction();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn($async);

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $rateLimitState = $this->createMock(RateLimitState::class);
        $rateLimitState->expects($this->once())->method('isLimited')->with('transaction')->willReturn(false);

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($async ? $this->once() : $this->never())->method('publish');

        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport->expects($async ? $this->never() : $this->once())
            ->method('send')
            ->willReturn(new Result(ResultStatus::success(), $event));

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper,
            $rateLimitState
        )->send($event);

        $this->assertSame((string) ResultStatus::success(), (string) $result->getStatus());
    }

    public function testQueueSetsTimestampWhenMissing(): void
    {
        $event = Event::createEvent();
        $event->setTimestamp(null);

        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(true);

        $before = microtime(true);
        $capturedTimestamp = null;

        $payloadSerializer = $this->createMock(PayloadSerializerInterface::class);
        $payloadSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($this->callback(static function (Event $event) use (&$capturedTimestamp): bool {
                $capturedTimestamp = $event->getTimestamp();

                return true;
            }))
            ->willReturn('payload');

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->once())->method('publish');

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $after = microtime(true);

        $this->assertIsFloat($capturedTimestamp);
        $this->assertGreaterThanOrEqual($before, $capturedTimestamp);
        $this->assertLessThanOrEqual($after, $capturedTimestamp);
        $this->assertSame($capturedTimestamp, $event->getTimestamp());
    }

    public function testQueuePreservesExistingTimestamp(): void
    {
        $existingTimestamp = 1_700_000_000.123456;
        $event = Event::createEvent();
        $event->setTimestamp($existingTimestamp);

        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(true);

        $payloadSerializer = $this->createMock(PayloadSerializerInterface::class);
        $payloadSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($this->callback(static function (Event $event) use ($existingTimestamp): bool {
                return $event->getTimestamp() === $existingTimestamp;
            }))
            ->willReturn('payload');

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->once())->method('publish');

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame($existingTimestamp, $event->getTimestamp());
    }

    public function testPublishExceptionReturnsFailed(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(true);

        $payloadSerializer = $this->createStub(PayloadSerializerInterface::class);
        $payloadSerializer->method('serialize')->willReturn('payload');

        $publisher = $this->createStub(SentryEventPublisher::class);
        $publisher->method('publish')->willThrowException(new RuntimeException('mq down'));

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $result = $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::failed(), (string) $result->getStatus());
    }

    public function testCloseDelegatesToHttpTransport(): void
    {
        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport
            ->expects($this->once())
            ->method('close')
            ->with(5)
            ->willReturn(new Result(ResultStatus::success()));

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $this->createStub(SentryEventPublisher::class),
            $this->createStub(CircuitBreaker::class),
            $this->createStub(Data::class)
        )->close(5);

        $this->assertSame((string) ResultStatus::success(), (string) $result->getStatus());
    }

    /**
     * @dataProvider failingStepProvider
     */
    public function testSendAfterFailedSendIsNotSkipped(string $failingStep): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(true);

        $payloadSerializer = $this->createStub(PayloadSerializerInterface::class);
        $payloadSerializer->method('serialize')->willReturn('payload');

        $calls = 0;
        $publisher = $this->createStub(SentryEventPublisher::class);
        $rateLimitState = $this->createStub(RateLimitState::class);
        match ($failingStep) {
            'publish'     => $publisher->method('publish')->willReturnCallback(
                static function () use (&$calls): void {
                    if (++$calls === 1) {
                        throw new RuntimeException('mq down');
                    }
                }
            ),
            'rate limits' => $rateLimitState->method('isLimited')->willReturnCallback(
                static function () use (&$calls): bool {
                    if (++$calls === 1) {
                        throw new RuntimeException('cache down');
                    }

                    return false;
                }
            ),
        };

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $transport = $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $helper,
            $rateLimitState
        );

        $failed = $transport->send(Event::createEvent());
        $retried = $transport->send(Event::createEvent());

        $this->assertSame((string) ResultStatus::failed(), (string) $failed->getStatus());
        $this->assertSame((string) ResultStatus::success(), (string) $retried->getStatus());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function failingStepProvider(): array
    {
        return [
            'publish'     => ['publish'],
            'rate limits' => ['rate limits'],
        ];
    }

    public function testNestedSendDuringPublishIsSkipped(): void
    {
        $event = Event::createEvent();
        $nestedEvent = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(true);

        $payloadSerializer = $this->createStub(PayloadSerializerInterface::class);
        $payloadSerializer->method('serialize')->willReturn('payload');

        $transport = null;
        $nestedResult = null;
        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function () use (&$transport, $nestedEvent, &$nestedResult): void {
                $this->assertInstanceOf(ResilientTransport::class, $transport);
                $nestedResult = $transport->send($nestedEvent);
            });

        $circuitBreaker = $this->createStub(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);

        $transport = $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
            $helper
        );

        $result = $transport->send($event);

        $this->assertSame((string) ResultStatus::success(), (string) $result->getStatus());
        $this->assertInstanceOf(\Sentry\Transport\Result::class, $nestedResult);
        $this->assertSame((string) ResultStatus::skipped(), (string) $nestedResult->getStatus());
    }
}
