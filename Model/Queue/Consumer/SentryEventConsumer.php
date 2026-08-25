<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Queue\Consumer;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\DeliveryGuard;
use JustBetter\Sentry\Model\Transport\EnvelopeSender;
use Throwable;

/**
 * Delivers queued Sentry envelopes as-is (fire-time timestamps already in payload).
 * Does not short-circuit on the circuit breaker — only records success/failure for the CB.
 */
class SentryEventConsumer
{
    /**
     * @param EnvelopeSender $envelopeSender
     * @param CircuitBreaker $circuitBreaker
     * @param Data           $helper
     * @param DeliveryGuard  $deliveryGuard
     */
    public function __construct(
        private readonly EnvelopeSender $envelopeSender,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly Data $helper,
        private readonly DeliveryGuard $deliveryGuard
    ) {
    }

    /**
     * Deliver a queued Sentry envelope and update the circuit breaker.
     *
     * @param string $payload Serialized Sentry envelope
     *
     * @throws Throwable Re-thrown so the queue framework can retry
     */
    public function process(string $payload): void
    {
        if (!$this->deliveryGuard->isActive()) {
            $this->deliveryGuard->enter();
        }

        if (!$this->helper->isActive() || $payload === '') {
            return;
        }

        try {
            $this->envelopeSender->send($payload);
            $this->circuitBreaker->recordSuccess();
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailure();

            throw $exception;
        }
    }
}
