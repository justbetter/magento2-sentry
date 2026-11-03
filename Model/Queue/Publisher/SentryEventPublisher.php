<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model\Queue\Publisher;

use Magento\Framework\MessageQueue\PublisherInterface;

/**
 * Publishes a pre-serialized Sentry envelope string to Magento MQ.
 */
class SentryEventPublisher
{
    public const TOPIC_NAME = 'justbetter.sentry.event.send';

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(
        private readonly PublisherInterface $publisher
    ) {
    }

    /**
     * Publish a serialized Sentry envelope to Magento MQ.
     *
     * @param string $payload Serialized Sentry envelope (event timestamp + sent_at frozen at fire time)
     */
    public function publish(string $payload): void
    {
        $this->publisher->publish(self::TOPIC_NAME, $payload);
    }
}
