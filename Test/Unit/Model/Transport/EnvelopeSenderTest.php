<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\Transport\EnvelopeSender;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EnvelopeSenderTest extends TestCase
{
    public function testEmptyPayloadThrows(): void
    {
        $sender = new EnvelopeSender($this->createStub(Data::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry envelope payload is empty.');

        $sender->send('');
    }

    public function testMissingDsnThrows(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('getDSN')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry DSN is not configured.');

        (new EnvelopeSender($helper))->send('not-empty');
    }

    public function testEmptyStringDsnThrows(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('getDSN')->willReturn('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry DSN is not configured.');

        (new EnvelopeSender($helper))->send('not-empty');
    }
}
