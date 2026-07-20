<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
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
        Data $helper
    ): ResilientTransport {
        return new ResilientTransport(
            $httpTransport,
            $payloadSerializer,
            $publisher,
            $circuitBreaker,
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
        $circuitBreaker->expects($this->never())->method('allowRequest');

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

    public function testSyncSuccessRecordsCircuitSuccess(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);
        $helper->method('isCircuitBreakerEnabled')->willReturn(true);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);
        $circuitBreaker->expects($this->once())->method('recordSuccess');
        $circuitBreaker->expects($this->never())->method('recordFailure');

        $httpTransport = $this->createMock(TransportInterface::class);
        $httpTransport
            ->expects($this->once())
            ->method('send')
            ->with($event)
            ->willReturn(new Result(ResultStatus::success(), $event));

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::success(), (string) $result->getStatus());
    }

    public function testSyncHttpExceptionFailsAndRecordsCircuitFailure(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);
        $helper->method('isCircuitBreakerEnabled')->willReturn(true);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);
        $circuitBreaker->expects($this->once())->method('recordFailure');

        $httpTransport = $this->createStub(TransportInterface::class);
        $httpTransport->method('send')->willThrowException(new RuntimeException('network down'));

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::failed(), (string) $result->getStatus());
    }

    public function testServerSideFailureRecordsAndReturnsFailed(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);
        $helper->method('isCircuitBreakerEnabled')->willReturn(true);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);
        $circuitBreaker->expects($this->once())->method('recordFailure');

        $httpTransport = $this->createStub(TransportInterface::class);
        $httpTransport->method('send')->willReturn(new Result(ResultStatus::failed(), $event));

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::failed(), (string) $result->getStatus());
    }

    public function testInvalidResultDoesNotTripCircuit(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);
        $helper->method('isCircuitBreakerEnabled')->willReturn(true);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->method('allowRequest')->willReturn(true);
        $circuitBreaker->expects($this->never())->method('recordFailure');
        $circuitBreaker->expects($this->never())->method('recordSuccess');

        $httpTransport = $this->createStub(TransportInterface::class);
        $httpTransport->method('send')->willReturn(new Result(ResultStatus::invalid(), $event));

        $publisher = $this->createMock(SentryEventPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $result = $this->createTransport(
            $httpTransport,
            $this->createStub(PayloadSerializerInterface::class),
            $publisher,
            $circuitBreaker,
            $helper
        )->send($event);

        $this->assertSame((string) ResultStatus::invalid(), (string) $result->getStatus());
    }

    public function testOpenCircuitReturnsFailedWithoutCallingHttp(): void
    {
        $event = Event::createEvent();
        $helper = $this->createStub(Data::class);
        $helper->method('isAsyncSendingEnabled')->willReturn(false);
        $helper->method('isCircuitBreakerEnabled')->willReturn(true);

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
        )->send($event);

        $this->assertSame((string) ResultStatus::failed(), (string) $result->getStatus());
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

        $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $this->createStub(CircuitBreaker::class),
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

        $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $this->createStub(CircuitBreaker::class),
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

        $result = $this->createTransport(
            $this->createStub(TransportInterface::class),
            $payloadSerializer,
            $publisher,
            $this->createStub(CircuitBreaker::class),
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
}
