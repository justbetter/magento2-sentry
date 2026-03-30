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
    public function beforeEnqueue(ExchangeInterface|BulkExchangeInterface $subject, ?string $topic, $envelopes): array // @phpstan-ignore missingType.iterableValue
    {
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if (!$parentSpan instanceof \Sentry\Tracing\Span) {
            return [$topic, $envelopes];
        }

        $isMultipleEnvelopes = is_array($envelopes);
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('queue.publish')
            ->setDescription($topic);

        $envelopes = array_map(function (EnvelopeInterface $envelope) use ($parentSpan, $context, $topic): \Magento\Framework\MessageQueue\EnvelopeInterface {
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
     * Modify the envelope body to include Sentry trace and baggage information.
     *
     * @param EnvelopeInterface $envelope
     *
     * @return void
     */
    protected function modifyEnvelope(EnvelopeInterface $envelope): void
    {
        $body = (string) json_encode([
            'envelope_body'       => $envelope->getBody(),
            'envelope_properties' => $envelope->getProperties(),
            'sentry_trace'        => \Sentry\getTraceparent(),
            'sentry_baggage'      => \Sentry\getBaggage(),
        ]);

        $this->modifyBody($envelope, $body);
    }

    /**
     * Use reflection to modify the body of the envelope.
     *
     * @param EnvelopeInterface $envelope
     * @param string            $body
     *
     * @return void
     */
    protected function modifyBody(EnvelopeInterface $envelope, string $body): void
    {
        $reflectedEnvelope = new \ReflectionObject($envelope);

        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedWhile
        while (!$reflectedEnvelope->hasProperty('body') && $reflectedEnvelope = $reflectedEnvelope->getParentClass()) {
        }

        if ($reflectedEnvelope && $reflectedEnvelope->hasProperty('body')) {
            $prop = $reflectedEnvelope->getProperty('body');
            $prop->setValue($envelope, $body);
        }
    }
}
