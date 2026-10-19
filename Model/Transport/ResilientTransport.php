<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use JustBetter\Sentry\Model\RateLimitState;
use Sentry\Event;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Throwable;

/**
 * Request-path transport: queue or short HTTP per configuration. Drops events while
 * Sentry is down or rate-limits their category.
 */
class ResilientTransport implements TransportInterface
{
    /**
     * @var bool
     */
    private bool $sending = false;

    /**
     * @param TransportInterface         $httpTransport
     * @param PayloadSerializerInterface $payloadSerializer
     * @param SentryEventPublisher       $publisher
     * @param CircuitBreaker             $circuitBreaker
     * @param RateLimitState             $rateLimitState
     * @param Data                       $helper
     */
    public function __construct(
        private readonly TransportInterface $httpTransport,
        private readonly PayloadSerializerInterface $payloadSerializer,
        private readonly SentryEventPublisher $publisher,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RateLimitState $rateLimitState,
        private readonly Data $helper
    ) {
    }

    /**
     * Send event via queue or HTTP depending on configuration, circuit state and rate limits.
     *
     * @param Event $event
     *
     * @return Result
     */
    public function send(Event $event): Result
    {
        // Avoid re-entry if publishing/logging triggers another capture.
        if ($this->sending) {
            return new Result(ResultStatus::skipped(), $event);
        }

        $this->sending = true;

        try {
            // Applies to both modes: sync fails fast, and the consumer would drop a queued copy anyway.
            if (!$this->circuitBreaker->allowRequest()) {
                return new Result(ResultStatus::failed(), $event);
            }

            if ($this->rateLimitState->isLimited((string) $event->getType())) {
                return new Result(ResultStatus::rateLimit(), $event);
            }

            return $this->shouldQueue()
                ? $this->queue($event)
                : $this->httpTransport->send($event);
        } catch (Throwable) {
            return new Result(ResultStatus::failed(), $event);
        } finally {
            $this->sending = false;
        }
    }

    /**
     * Close the underlying HTTP transport.
     *
     * @param int|null $timeout
     *
     * @return Result
     */
    public function close(?int $timeout = null): Result
    {
        return $this->httpTransport->close($timeout);
    }

    /**
     * Whether the event should be published to the queue.
     */
    private function shouldQueue(): bool
    {
        return $this->helper->isAsyncSendingEnabled();
    }

    /**
     * Serialize at fire time and enqueue the envelope bytes.
     *
     * Consumer POSTs this as-is, so Sentry keeps payload "timestamp" / "sent_at".
     *
     * @param Event $event
     */
    private function queue(Event $event): Result
    {
        if ($event->getTimestamp() === null) {
            $event->setTimestamp(microtime(true));
        }

        $this->publisher->publish($this->payloadSerializer->serialize($event));

        return new Result(ResultStatus::success(), $event);
    }
}
