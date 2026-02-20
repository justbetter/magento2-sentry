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

            $this->modifyEnvelope($envelope);

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
     * Inject Sentry trace and baggage headers into the envelope's properties.
     *
     * @param EnvelopeInterface $envelope
     */
    protected function modifyEnvelope(EnvelopeInterface $envelope): void
    {
        $reflectedEnvelope = new \ReflectionObject($envelope);

        while ($reflectedEnvelope && !$reflectedEnvelope->hasProperty('properties')) {
            $reflectedEnvelope = $reflectedEnvelope->getParentClass();
        }

        if (!$reflectedEnvelope) {
            throw new \RuntimeException('Envelope class does not have a "properties" field.');
        }

        $prop = $reflectedEnvelope->getProperty('properties');
        $prop->setValue($envelope, [
            ...$prop->getValue($envelope),
            'sentry_trace'   => \Sentry\getTraceparent(),
            'sentry_baggage' => \Sentry\getBaggage(),
        ]);
    }
}
