<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\RateLimitParser;
use JustBetter\Sentry\Model\RateLimitState;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\Response;

class RateLimitStateTest extends TestCase
{
    private const CACHE_KEY = 'justbetter_sentry_rate_limits';

    /**
     * @var array<string, string>
     */
    private array $storage = [];

    protected function setUp(): void
    {
        $this->storage = [];
    }

    private function createState(?CacheInterface $cache = null): RateLimitState
    {
        if (!$cache) {
            $cache = $this->createStub(CacheInterface::class);
            $cache->method('load')->willReturnCallback(fn (string $key): string|false => $this->storage[$key] ?? false);
            $cache->method('save')->willReturnCallback(function (string $data, string $key): bool {
                $this->storage[$key] = $data;

                return true;
            });
        }

        $helper = $this->createStub(Data::class);
        $helper->method('getCircuitBreakerRecoveryTimeout')->willReturn(60);

        return new RateLimitState($cache, new Json(), new RateLimitParser(), $helper);
    }

    /**
     * @param array<string, int> $limits
     */
    private function storeLimits(array $limits): void
    {
        $this->storage[self::CACHE_KEY] = (string) json_encode($limits);
    }

    /**
     * @return array<string, int>
     */
    private function storedLimits(): array
    {
        return json_decode($this->storage[self::CACHE_KEY], true);
    }

    /**
     * @dataProvider itemTypeProvider
     */
    public function testLimitAppliesOnlyToItsOwnCategory(string $limitedCategory, string $itemType): void
    {
        $state = $this->createState();
        $state->record(new Response(429, ['X-Sentry-Rate-Limits' => ["60:$limitedCategory:organization"]], ''));

        $this->assertTrue($state->isLimited($itemType));
        foreach (['event', 'transaction', 'check_in', 'log'] as $otherType) {
            if ($otherType !== $itemType) {
                $this->assertFalse($state->isLimited($otherType), "$otherType must not be limited");
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function itemTypeProvider(): array
    {
        return [
            'errors'          => ['error', 'event'],
            'transactions'    => ['transaction', 'transaction'],
            'cron check-ins'  => ['monitor', 'check_in'],
            'logs'            => ['log_item', 'log'],
        ];
    }

    public function testAllCategoriesLimitEveryItemType(): void
    {
        $this->storeLimits(['all' => time() + 60]);
        $state = $this->createState();

        foreach (['event', 'transaction', 'check_in', 'log', ''] as $itemType) {
            $this->assertTrue($state->isLimited($itemType), "'$itemType' must be limited");
        }
    }

    public function testUnknownItemTypeIsOnlyLimitedByAllCategories(): void
    {
        $this->storeLimits(['error' => time() + 60]);

        $this->assertFalse($this->createState()->isLimited(''));
    }

    public function testExpiredLimitDoesNotBlock(): void
    {
        $this->storeLimits(['error' => time() - 1]);

        $this->assertFalse($this->createState()->isLimited('event'));
    }

    /**
     * @dataProvider unusableCacheProvider
     */
    public function testUnusableCacheMeansNoLimits(string|false $cached): void
    {
        if ($cached !== false) {
            $this->storage[self::CACHE_KEY] = $cached;
        }

        $this->assertFalse($this->createState()->isLimited('event'));
    }

    /**
     * @return array<string, array{0: string|false}>
     */
    public static function unusableCacheProvider(): array
    {
        return [
            'nothing cached'  => [false],
            'empty string'    => [''],
            'corrupt json'    => ['not-json'],
            'json non-array'  => ['"error"'],
        ];
    }

    public function testBare429LimitsAllCategoriesForRecoveryTimeout(): void
    {
        $this->createState()->record(new Response(429, [], ''));

        $this->assertSame(['all'], array_keys($this->storedLimits()));
        $this->assertEqualsWithDelta(time() + 60, $this->storedLimits()['all'], 2);
    }

    public function testAcceptedResponseWithLimitsHeaderIsRecorded(): void
    {
        $state = $this->createState();
        $state->record(new Response(200, ['X-Sentry-Rate-Limits' => ['60:profile:organization']], ''));

        $this->assertTrue($state->isLimited('profile'));
    }

    /**
     * @dataProvider nothingToRecordProvider
     *
     * @param array<string, array<int, string>> $headers
     */
    public function testNothingIsSavedWithoutActiveLimits(int $statusCode, array $headers): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects($this->never())->method('save');

        $this->createState($cache)->record(new Response($statusCode, $headers, ''));
    }

    /**
     * @return array<string, array{0: int, 1: array<string, array<int, string>>}>
     */
    public static function nothingToRecordProvider(): array
    {
        return [
            'accepted without headers'     => [200, []],
            'rejected payload'             => [400, []],
            'limit that already ran out'   => [429, ['X-Sentry-Rate-Limits' => ['0:error:organization']]],
        ];
    }

    public function testRecordMergesWithStoredLimitsAndDropsExpiredOnes(): void
    {
        $now = time();
        $this->storeLimits([
            'error'       => $now + 300,
            'transaction' => $now + 30,
            'monitor'     => $now - 1,
        ]);

        $this->createState()->record(new Response(
            429,
            ['X-Sentry-Rate-Limits' => ['60:error:organization,120:transaction:organization']],
            ''
        ));

        $stored = $this->storedLimits();
        $this->assertEqualsCanonicalizing(['error', 'transaction'], array_keys($stored));
        $this->assertEqualsWithDelta($now + 300, $stored['error'], 2, 'later stored limit must win');
        $this->assertEqualsWithDelta($now + 120, $stored['transaction'], 2, 'later new limit must win');
    }

    public function testCacheLifetimeCoversTheLatestLimit(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects($this->once())
            ->method('save')
            ->with($this->anything(), self::CACHE_KEY, $this->anything(), $this->logicalAnd(
                $this->greaterThanOrEqual(598),
                $this->lessThanOrEqual(600)
            ));

        $this->createState($cache)->record(new Response(
            429,
            ['X-Sentry-Rate-Limits' => ['60:error:organization,600:transaction:organization']],
            ''
        ));
    }
}
