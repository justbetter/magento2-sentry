<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use Sentry\Event;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Throwable;

/**
 * Request-path transport: queue when configured / circuit open, otherwise short HTTP.
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
     * @param Data                       $helper
     */
    public function __construct(
        private readonly TransportInterface $httpTransport,
        private readonly PayloadSerializerInterface $payloadSerializer,
        private readonly SentryEventPublisher $publisher,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly Data $helper
    ) {
    }

    /**
     * Send event via queue or HTTP depending on configuration and circuit state.
     *
     * @param Event $event
     */
    public function send(Event $event): Result
    {
        // Avoid re-entry if publishing/logging triggers another capture.
        if ($this->sending) {
            return new Result(ResultStatus::skipped(), $event);
        }

        $this->sending = true;

        try {
            return $this->shouldQueue()
                ? $this->queue($event)
                : $this->sendHttp($event);
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
     * Attempt synchronous HTTP delivery and update the circuit breaker.
     *
     * @param Event $event
     */
    private function sendHttp(Event $event): Result
    {
        if (!$this->circuitBreaker->allowRequest()) {
            return new Result(ResultStatus::failed(), $event);
        }

        try {
            $result = $this->httpTransport->send($event);
        } catch (Throwable) {
            $this->circuitBreaker->recordFailure();

            return new Result(ResultStatus::failed(), $event);
        }

        if ($this->isSuccess($result)) {
            $this->circuitBreaker->recordSuccess();

            return $result;
        }

        if ($this->isServerSideFailure($result)) {
            $this->circuitBreaker->recordFailure();

            return $result;
        }

        return $result;
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

    /**
     * Whether the transport result indicates success.
     *
     * @param Result $result
     */
    private function isSuccess(Result $result): bool
    {
        return (string) $result->getStatus() === (string) ResultStatus::success();
    }

    /**
     * Whether the transport result should count as a circuit-breaker failure.
     *
     * @param Result $result
     */
    private function isServerSideFailure(Result $result): bool
    {
        return in_array((string) $result->getStatus(), [
            (string) ResultStatus::failed(),
            (string) ResultStatus::unknown(),
            (string) ResultStatus::rateLimit(),
        ], true);
    }
}
