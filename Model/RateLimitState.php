<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Sentry\HttpClient\Response;

/**
 * Cache-backed Sentry rate limits per data category, shared across processes.
 *
 * Unlike \Sentry\Transport\RateLimiter it survives the request, so producers stop queueing
 * what the consumer was told to hold back. A limit on one category never blocks the others.
 */
class RateLimitState
{
    private const CACHE_KEY = 'justbetter_sentry_rate_limits';
    private const CACHE_TAG = 'JUSTBETTER_SENTRY_RATE_LIMITS';

    /**
     * Envelope item type -> Sentry data category, where they differ.
     *
     * @see \Sentry\Transport\RateLimiter::getDisabledUntil()
     */
    private const CATEGORY_BY_ITEM_TYPE = [
        'event'    => 'error',
        'check_in' => 'monitor',
        'log'      => 'log_item',
    ];

    /**
     * @param CacheInterface  $cache
     * @param Json            $serializer
     * @param RateLimitParser $parser
     * @param Data            $helper
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $serializer,
        private readonly RateLimitParser $parser,
        private readonly Data $helper
    ) {
    }

    /**
     * Remember the limits a Sentry response announces; a bare 429 limits every category.
     *
     * @param Response $response
     */
    public function record(Response $response): void
    {
        $limits = $this->parser->parse($response);
        if (!$limits && $response->getStatusCode() === 429) {
            $limits = [RateLimitParser::ALL_CATEGORIES => time() + $this->helper->getCircuitBreakerRecoveryTimeout()];
        }

        if (!$limits) {
            return;
        }

        $now = time();
        $merged = $this->load();
        foreach ($limits as $category => $retryAt) {
            $merged[$category] = max($merged[$category] ?? 0, $retryAt);
        }
        $merged = array_filter($merged, static fn (int $retryAt): bool => $retryAt > $now);
        if (!$merged) {
            return;
        }

        $this->cache->save(
            (string) $this->serializer->serialize($merged),
            self::CACHE_KEY,
            [self::CACHE_TAG],
            max($merged) - $now
        );
    }

    /**
     * Whether Sentry currently refuses this envelope item type.
     *
     * @param string $itemType Envelope item type, same values as \Sentry\EventType
     */
    public function isLimited(string $itemType): bool
    {
        $limits = $this->load();
        $category = self::CATEGORY_BY_ITEM_TYPE[$itemType] ?? $itemType;

        return max($limits[RateLimitParser::ALL_CATEGORIES] ?? 0, $limits[$category] ?? 0) > time();
    }

    /**
     * Read on every call: long-running consumers must see limits recorded by other processes.
     *
     * @return array<string, int>
     */
    private function load(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if (!is_string($cached) || $cached === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($cached);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }
}
