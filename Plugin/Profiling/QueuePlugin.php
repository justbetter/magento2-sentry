<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\QueueInterface;
use Sentry\Tracing\Span;

class QueuePlugin
{
    /** @var ?Span $parentSpan */
    private ?Span $parentSpan = null;

    /** @var \Sentry\Tracing\Transaction[] $transactions */
    private array $transactions = [];

    /**
     * Start transaction for job.
     *
     * @param QueueInterface $queue
     * @param ?EnvelopeInterface $envelope
     *
     * @return ?EnvelopeInterface
     */
    public function afterDequeue(QueueInterface $queue, ?EnvelopeInterface $envelope): ?EnvelopeInterface
    {
        if ($envelope === null) {
            return $envelope;
        }

        $properties = $envelope->getProperties();
        $body = json_decode($envelope->getBody(), true);
        if (!isset($body['sentry_trace']) && !isset($body['sentry_baggage'])) {
            return $envelope;
        }

        $this->parentSpan ??= \Sentry\SentrySdk::getCurrentHub()->getSpan();

        $context = \Sentry\continueTrace(
            $body['sentry_trace'],
            $body['sentry_baggage']
        )
            ->setOp('queue.process')
            ->setName($properties['topic_name']);

        $this->transactions[$properties['message_id']] = \Sentry\startTransaction($context);
        $this->transactions[$properties['message_id']]
            ->setData([
                'messaging.message.id' => $properties['message_id'],
                'messaging.destination.name' => $properties['topic_name'],
                'messaging.queue.name' => $properties['queue_name'],
                'messaging.message.body.size' => strlen($envelope->getBody()),
                'messaging.message.retry.count' => $properties['retries']
            ]);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($this->transactions[$properties['message_id']]);
        
        unset($body['sentry_trace']);
        unset($body['sentry_baggage']);
        return $this->setBody($envelope, json_encode($body));
    }

    /**
     * Finish transaction for failed job.
     *
     * @param QueueInterface $queue
     * @param EnvelopeInterface $envelope
     * @param bool $requeue
     * @param string $rejectionMessage
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
     * @param QueueInterface $queue
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
     * @param string $body
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
