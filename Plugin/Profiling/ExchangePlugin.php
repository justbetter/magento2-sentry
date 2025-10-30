<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use Magento\Framework\MessageQueue\Bulk\ExchangeInterface as BulkExchangeInterface;
use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\ExchangeInterface;

class ExchangePlugin
{
    /**
     * Method creates a Sentry span for queue monitoring.
     *
     * @param ExchangeInterface|BulkExchangeInterface $subject
     * @param string                                  $topic
     * @param EnvelopeInterface|EnvelopeInterface[]   $envelopes
     *
     * @return array
     */
    public function beforeEnqueue(ExchangeInterface|BulkExchangeInterface $subject, $topic, $envelopes): array
    {
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return [$topic, $envelopes];
        }

        $isMultipleEnvelopes = is_array($envelopes);
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('queue.publish')
            ->setDescription($topic);

        $envelopes = array_map(function (EnvelopeInterface $envelope) use ($parentSpan, $context, $topic) {
            $properties = $envelope->getProperties();
            $span = $parentSpan->startChild($context);
            \Sentry\SentrySdk::getCurrentHub()->setSpan($span);

            $body = json_decode($envelope->getBody(), true);
            $envelope = $this->setBody(
                $envelope,
                json_encode([
                    ...$body,
                    'sentry_trace'   => \Sentry\getTraceparent(),
                    'sentry_baggage' => \Sentry\getBaggage(),
                ])
            );

            $span
                ->setData([
                    'messaging.message.id'        => $properties['message_id'] ?? null,
                    'messaging.destination.name'  => $topic,
                    'messaging.message.body.size' => strlen($envelope->getBody()),
                ])
                ->finish();

            \Sentry\SentrySdk::getCurrentHub()->setSpan($parentSpan);

            return $envelope;
        }, $isMultipleEnvelopes ? $envelopes : [$envelopes]);

        $envelopes = $isMultipleEnvelopes ? $envelopes : $envelopes[0];

        return [$topic, $envelopes];
    }

    /**
     * Attempt to set the body to the private body variable.
     *
     * @param EnvelopeInterface $envelope
     * @param string            $body
     *
     * @return EnvelopeInterface
     */
    protected function setBody(EnvelopeInterface $envelope, string $body): EnvelopeInterface
    {
        $reflectedEnvelope = new \ReflectionObject($envelope);
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedWhile
        while (!$reflectedEnvelope->hasProperty('body') && $reflectedEnvelope = $reflectedEnvelope->getParentClass()) {
        }

        if ($reflectedEnvelope && $reflectedEnvelope->hasProperty('body')) {
            $prop = $reflectedEnvelope->getProperty('body');
            $prop->setAccessible(true);
            $prop->setValue($envelope, $body);
        }

        return $envelope;
    }
}
