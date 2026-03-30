<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\QueueInterface;
use Sentry\Tracing\Span;

class QueuePlugin
{
    /** @var ?Span */
    private ?Span $parentSpan = null;

    /** @var \Sentry\Tracing\Transaction[] */
    private array $transactions = [];

    /**
     * Modifies the envelope body to include envelope properties to be used for transaction context.
     *
     * @param QueueInterface     $queue
     * @param ?EnvelopeInterface $envelope
     *
     * @return ?EnvelopeInterface
     */
    public function afterDequeue(QueueInterface $queue, ?EnvelopeInterface $envelope): ?EnvelopeInterface
    {
        if ($envelope === null) {
            return $envelope;
        }

        $body = json_decode($envelope->getBody(), true);
        if (!isset($body['sentry_trace']) && !isset($body['sentry_baggage'])) {
            return $envelope;
        }

        $this->parentSpan ??= \Sentry\SentrySdk::getCurrentHub()->getSpan();

        $body['envelope_properties'] = $envelope->getProperties();

        return $this->setBody($envelope, (string) json_encode($body));
    }

    /**
     * Finish transaction for failed job.
     *
     * @param QueueInterface    $queue
     * @param EnvelopeInterface $envelope
     * @param bool              $requeue
     * @param string            $rejectionMessage
     *
     * @return array
     */
    public function beforeReject(QueueInterface $queue, EnvelopeInterface $envelope, $requeue = true, $rejectionMessage = null): array
    {
        $properties = $envelope->getProperties();
        $transaction = $this->transactions[$properties['message_id']] ?? null;
        if (!$transaction) {
            return [$envelope, $requeue, $rejectionMessage];
        }

        $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());

        $transaction->finish();
        unset($this->transactions[$properties['message_id']]);
        if ($this->parentSpan) {
            \Sentry\SentrySdk::getCurrentHub()->setSpan($this->parentSpan);
        }

        return [$envelope, $requeue, $rejectionMessage];
    }

    /**
     * Finish transaction for successfully executed job.
     *
     * @param QueueInterface    $queue
     * @param EnvelopeInterface $envelope
     *
     * @return array
     */
    public function beforeAcknowledge(QueueInterface $queue, EnvelopeInterface $envelope): array
    {
        $properties = $envelope->getProperties();
        $transaction = $this->transactions[$properties['message_id']] ?? null;
        if (!$transaction) {
            return [$envelope];
        }

        $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());

        $transaction->finish();
        unset($this->transactions[$properties['message_id']]);
        if ($this->parentSpan) {
            \Sentry\SentrySdk::getCurrentHub()->setSpan($this->parentSpan);
        }

        return [$envelope];
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
