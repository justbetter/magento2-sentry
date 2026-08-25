<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Queue\Publisher;

use JustBetter\Sentry\Model\Queue\Publisher\SentryEventPublisher;
use Magento\Framework\MessageQueue\PublisherInterface;
use PHPUnit\Framework\TestCase;

class SentryEventPublisherTest extends TestCase
{
    public function testPublishSendsPayloadOnTopic(): void
    {
        $payload = "{\"sent_at\":\"2026-01-01T00:00:00Z\"}\n{\"type\":\"event\"}";

        $magentoPublisher = $this->createMock(PublisherInterface::class);
        $magentoPublisher
            ->expects($this->once())
            ->method('publish')
            ->with(SentryEventPublisher::TOPIC_NAME, $payload);

        $publisher = new SentryEventPublisher($magentoPublisher);
        $publisher->publish($payload);
    }

    public function testTopicNameIsStable(): void
    {
        $this->assertSame('justbetter.sentry.event.send', SentryEventPublisher::TOPIC_NAME);
    }
}
