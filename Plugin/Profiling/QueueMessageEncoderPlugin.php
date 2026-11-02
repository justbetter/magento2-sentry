<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use Magento\Framework\MessageQueue\MessageEncoder;

class QueueMessageEncoderPlugin
{
    /**
     * Start transaction for job.
     *
     * @param MessageEncoder $subject
     * @param string         $topic
     * @param string         $message
     * @param bool           $requestType
     *
     * @return array{0:string,1:string,2:bool}
     */
    public function beforeDecode(MessageEncoder $subject, string $topic, $message, $requestType = true): array
    {
        $body = json_decode($message, true);

        if (!isset($body['sentry_trace']) && !isset($body['sentry_baggage'])) {
            return [$topic, $message, $requestType];
        }

        $properties = $body['envelope_properties'];

        $context = \Sentry\continueTrace(
            $body['sentry_trace'],
            $body['sentry_baggage']
        )
            ->setOp('queue.process')
            ->setName($topic);

        $transaction = \Sentry\startTransaction($context);
        $transaction->setData([
            'messaging.message.id'          => $properties['message_id'],
            'messaging.destination.name'    => $topic,
            'messaging.queue.name'          => $properties['queue_name'] ?? null,
            'messaging.message.body.size'   => strlen($message),
            'messaging.message.retry.count' => $properties['retries'] ?? null,
        ]);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        unset(
            $body['envelope_properties'],
            $body['sentry_trace'],
            $body['sentry_baggage']
        );

        return [
            $topic,
            $body['envelope_body'] ?? (string) json_encode($body),
            $requestType,
        ];
    }
}
