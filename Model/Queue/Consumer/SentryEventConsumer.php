<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Queue\Consumer;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use JustBetter\Sentry\Model\RateLimitState;
use JustBetter\Sentry\Model\Transport\EnvelopeSender;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Delivers queued Sentry envelopes as-is (fire-time timestamps already in payload).
 *
 * Never throws: Magento would reject the message without requeue anyway, and log it
 * through a Sentry-attached logger, which feeds the failure back into this queue.
 */
class SentryEventConsumer
{
    /**
     * @param EnvelopeSender  $envelopeSender
     * @param CircuitBreaker  $circuitBreaker
     * @param RateLimitState  $rateLimitState
     * @param Data            $helper
     * @param LoggerInterface $logger         File-only channel, excluded from Sentry by MonologPlugin
     */
    public function __construct(
        private readonly EnvelopeSender $envelopeSender,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RateLimitState $rateLimitState,
        private readonly Data $helper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Deliver a queued Sentry envelope; drop it while the circuit is open or its category is rate-limited.
     *
     * @param string $payload Serialized Sentry envelope
     */
    public function process(string $payload): void
    {
        try {
            if (!$this->helper->isActive()
                || $payload === ''
                || !$this->circuitBreaker->allowRequest()
                || $this->rateLimitState->isLimited($this->getItemType($payload))
            ) {
                return;
            }

            $this->envelopeSender->send($payload);
        } catch (Throwable $exception) {
            $this->logger->warning('Sentry envelope delivery failed', ['exception' => $exception]);
        }
    }

    /**
     * Item type from the envelope's item header; empty if unreadable, so only global limits apply.
     * SDK envelopes hold one item: "<envelope header>\n<item header>\n<item payload>".
     *
     * @see \Sentry\Serializer\PayloadSerializer::serialize()
     *
     * @param string $payload
     *
     * @return string
     */
    private function getItemType(string $payload): string
    {
        // [envelope header, item header, rest]
        $lines = explode("\n", $payload, 3);
        if (!isset($lines[1])) {
            return '';
        }

        $itemHeader = json_decode($lines[1], true);

        return is_array($itemHeader) && is_string($itemHeader['type'] ?? null) ? $itemHeader['type'] : '';
    }
}
