<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Plugin;

use JustBetter\Sentry\Logger\Handler\Sentry;
use JustBetter\Sentry\Plugin\MonologPlugin;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class MonologPluginTest extends TestCase
{
    /**
     * @var Sentry
     */
    private Sentry $sentryHandler;

    protected function setUp(): void
    {
        $this->sentryHandler = $this->createMock(Sentry::class);
    }

    public function testAddsSentryHandlerWhenNotAlreadyPresent(): void
    {
        $logger = new Logger('some_channel');

        $result = (new MonologPlugin($this->sentryHandler))->beforeSetHandlers($logger, []);

        $this->assertSame([[$this->sentryHandler]], $result);
    }

    public function testDoesNotDuplicateExistingSentryHandler(): void
    {
        $logger = new Logger('some_channel');

        $result = (new MonologPlugin($this->sentryHandler))->beforeSetHandlers($logger, [$this->sentryHandler]);

        $this->assertSame([[$this->sentryHandler]], $result);
    }

    public function testLeavesHandlersUntouchedForExcludedSentryDeliveryChannel(): void
    {
        $logger = new Logger(MonologPlugin::EXCLUDED_CHANNEL);
        $streamHandler = $this->createMock(StreamHandler::class);

        $result = (new MonologPlugin($this->sentryHandler))->beforeSetHandlers($logger, [$streamHandler]);

        $this->assertSame([[$streamHandler]], $result);
    }
}
