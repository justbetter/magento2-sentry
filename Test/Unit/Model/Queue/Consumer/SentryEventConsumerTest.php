<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Queue\Consumer;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\DeliveryGuard;
use JustBetter\Sentry\Model\Queue\Consumer\SentryEventConsumer;
use JustBetter\Sentry\Model\Transport\EnvelopeSender;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SentryEventConsumerTest extends TestCase
{
    public function testSkipsWhenModuleInactive(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isActive')->willReturn(false);

        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->never())->method('send');

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->expects($this->never())->method('recordSuccess');
        $circuitBreaker->expects($this->never())->method('recordFailure');

        $guard = new DeliveryGuard();
        $consumer = new SentryEventConsumer($envelopeSender, $circuitBreaker, $helper, $guard);
        $consumer->process('payload');
        $this->assertTrue($guard->isActive());
    }

    public function testSkipsEmptyPayload(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isActive')->willReturn(true);

        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender->expects($this->never())->method('send');

        $guard = new DeliveryGuard();
        $consumer = new SentryEventConsumer(
            $envelopeSender,
            $this->createStub(CircuitBreaker::class),
            $helper,
            $guard
        );
        $consumer->process('');
        $this->assertTrue($guard->isActive());
    }

    public function testSuccessfulDeliveryRecordsSuccess(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isActive')->willReturn(true);

        $envelopeSender = $this->createMock(EnvelopeSender::class);
        $envelopeSender
            ->expects($this->once())
            ->method('send')
            ->with('envelope-bytes');

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->expects($this->once())->method('recordSuccess');
        $circuitBreaker->expects($this->never())->method('recordFailure');
        $circuitBreaker->expects($this->never())->method('allowRequest');

        $guard = new DeliveryGuard();
        $consumer = new SentryEventConsumer($envelopeSender, $circuitBreaker, $helper, $guard);
        $consumer->process('envelope-bytes');
        $this->assertTrue($guard->isActive());
    }

    public function testFailureRecordsAndRethrows(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isActive')->willReturn(true);

        $exception = new RuntimeException('sentry 503');
        $envelopeSender = $this->createStub(EnvelopeSender::class);
        $envelopeSender->method('send')->willThrowException($exception);

        $circuitBreaker = $this->createMock(CircuitBreaker::class);
        $circuitBreaker->expects($this->once())->method('recordFailure');
        $circuitBreaker->expects($this->never())->method('recordSuccess');

        $this->expectExceptionObject($exception);

        $guard = new DeliveryGuard();
        $consumer = new SentryEventConsumer($envelopeSender, $circuitBreaker, $helper, $guard);

        try {
            $consumer->process('envelope-bytes');
        } finally {
            $this->assertTrue($guard->isActive());
        }
    }
}
