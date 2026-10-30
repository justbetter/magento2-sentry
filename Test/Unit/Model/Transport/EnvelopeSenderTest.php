<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model\Transport;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\Transport\EnvelopeSender;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Response;

class EnvelopeSenderTest extends TestCase
{
    private function createHelper(): Data
    {
        $helper = $this->createStub(Data::class);
        $helper->method('getDSN')->willReturn('https://key@sentry.example/1');

        return $helper;
    }

    private function createSender(int $statusCode, string $body = ''): EnvelopeSender
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response($statusCode, [], $body));

        return new EnvelopeSender($this->createHelper(), $httpClient);
    }

    public function testEmptyPayloadThrows(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry envelope payload is empty.');

        (new EnvelopeSender($this->createHelper(), $httpClient))->send('');
    }

    /**
     * @dataProvider missingDsnProvider
     */
    public function testMissingDsnThrows(?string $dsn): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('getDSN')->willReturn($dsn);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry DSN is not configured.');

        (new EnvelopeSender($helper, $httpClient))->send('not-empty');
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function missingDsnProvider(): array
    {
        return [
            'null dsn'  => [null],
            'empty dsn' => [''],
        ];
    }

    /**
     * @dataProvider silentStatusCodeProvider
     */
    public function testDoesNotThrow(int $statusCode): void
    {
        $this->createSender($statusCode)->send('envelope-bytes');

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function silentStatusCodeProvider(): array
    {
        return [
            'accepted'     => [200],
            'rate limited' => [429],
        ];
    }

    /**
     * @dataProvider failedStatusCodeProvider
     */
    public function testFailedDeliveryThrows(int $statusCode, string $expectedMessage): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->createSender($statusCode, 'boom')->send('envelope-bytes');
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function failedStatusCodeProvider(): array
    {
        return [
            '5xx server error'      => [503, 'Sentry envelope delivery failed with HTTP 503 (FAILED): boom'],
            'unrecognized status 0' => [0, 'Sentry envelope delivery failed with HTTP 0 (UNKNOWN): boom'],
            '413 content too large' => [413, 'Sentry envelope delivery failed with HTTP 413 (CONTENT_TOO_LARGE): boom'],
            '400 invalid'           => [400, 'Sentry envelope delivery failed with HTTP 400 (INVALID): boom'],
        ];
    }
}
