<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model;

use JustBetter\Sentry\Model\RateLimitParser;
use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\Response;

class RateLimitParserTest extends TestCase
{
    /**
     * @dataProvider limitsProvider
     *
     * @param array<string, array<int, string>> $headers
     * @param array<string, int>                $expectedOffsets Seconds from now, keyed by category
     */
    public function testParse(array $headers, array $expectedOffsets): void
    {
        $limits = (new RateLimitParser())->parse(new Response(429, $headers, ''));

        $this->assertSame(array_keys($expectedOffsets), array_keys($limits));
        foreach ($expectedOffsets as $category => $offset) {
            $this->assertEqualsWithDelta(time() + $offset, $limits[$category], 2);
        }
    }

    /**
     * @return array<string, array{0: array<string, array<int, string>>, 1: array<string, int>}>
     */
    public static function limitsProvider(): array
    {
        return [
            'single category'                         => [['X-Sentry-Rate-Limits' => ['120:error:key']], ['error' => 120]],
            'entries for different categories'        => [
                ['X-Sentry-Rate-Limits' => ['30:error:key,90:transaction:key']],
                ['error' => 30, 'transaction' => 90],
            ],
            'several categories in one entry'         => [
                ['X-Sentry-Rate-Limits' => ['60:transaction;span:organization']],
                ['transaction' => 60, 'span' => 60],
            ],
            'same category twice keeps the later one' => [
                ['X-Sentry-Rate-Limits' => ['90:error:key,30:error:organization']],
                ['error' => 90],
            ],
            'whitespace after commas'                 => [
                ['X-Sentry-Rate-Limits' => ['30:error:key, 90:transaction:key']],
                ['error' => 30, 'transaction' => 90],
            ],
            'whitespace after semicolons'             => [
                ['X-Sentry-Rate-Limits' => ['60:error; transaction:organization']],
                ['error' => 60, 'transaction' => 60],
            ],
            'namespaces segment is ignored'           => [
                ['X-Sentry-Rate-Limits' => ['60:metric_bucket:organization:quota_exceeded:custom']],
                ['metric_bucket' => 60],
            ],
            'empty categories limit everything'       => [['X-Sentry-Rate-Limits' => ['60::organization']], ['all' => 60]],
            'missing categories limit everything'     => [['X-Sentry-Rate-Limits' => ['60']], ['all' => 60]],
            'non-digit retry_after is skipped'        => [
                ['X-Sentry-Rate-Limits' => ['abc:error:key,45:transaction:key']],
                ['transaction' => 45],
            ],
            'only non-digit entries'                   => [['X-Sentry-Rate-Limits' => ['abc:error:key']], []],
            'rate limits header wins over retry-after' => [
                ['X-Sentry-Rate-Limits' => ['30:error:key'], 'Retry-After' => ['600']],
                ['error' => 30],
            ],
            'retry-after numeric seconds'             => [['Retry-After' => ['30']], ['all' => 30]],
            'retry-after rfc1123 past date'           => [
                ['Retry-After' => [(new \DateTimeImmutable('-1 year'))->format(\DateTimeImmutable::RFC1123)]],
                [],
            ],
            'retry-after unparseable value'           => [['Retry-After' => ['not-a-date']], []],
            'no rate-limit headers at all'            => [[], []],
        ];
    }

    public function testRetryAfterFutureHttpDateIsUsedAsIs(): void
    {
        $retryAt = new \DateTimeImmutable('+1 year');

        $limits = (new RateLimitParser())->parse(
            new Response(429, ['Retry-After' => [$retryAt->format(\DateTimeImmutable::RFC1123)]], '')
        );

        $this->assertSame(['all' => $retryAt->getTimestamp()], $limits);
    }
}
