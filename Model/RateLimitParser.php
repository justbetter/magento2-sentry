<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use DateTimeImmutable;
use Sentry\HttpClient\Response;

/**
 * Reads per-category back-off times from Sentry rate-limit headers.
 *
 * @see \Sentry\Transport\RateLimiter::handleResponse() header semantics
 */
class RateLimitParser
{
    public const ALL_CATEGORIES = 'all';

    private const RATE_LIMITS_HEADER = 'X-Sentry-Rate-Limits';
    private const RETRY_AFTER_HEADER = 'Retry-After';

    /**
     * Unix timestamps until which Sentry asked us to stop sending, keyed by data category.
     *
     * @param Response $response
     *
     * @return array<string, int>
     */
    public function parse(Response $response): array
    {
        $now = time();

        if ($response->hasHeader(self::RATE_LIMITS_HEADER)) {
            $limits = [];
            // Entry format: retry_after:categories:scope:reason_code[:namespaces]
            foreach (explode(',', $response->getHeaderLine(self::RATE_LIMITS_HEADER)) as $limit) {
                $parameters = explode(':', trim($limit), 3);
                if (!ctype_digit($parameters[0])) {
                    continue;
                }

                $retryAt = $now + (int) $parameters[0];
                foreach (explode(';', $parameters[1] ?? '') as $category) {
                    $category = trim($category) ?: self::ALL_CATEGORIES;
                    $limits[$category] = max($limits[$category] ?? 0, $retryAt);
                }
            }

            return $limits;
        }

        if ($response->hasHeader(self::RETRY_AFTER_HEADER)) {
            $retryAt = $this->parseRetryAfter(trim($response->getHeaderLine(self::RETRY_AFTER_HEADER)), $now);

            return $retryAt ? [self::ALL_CATEGORIES => $retryAt] : [];
        }

        return [];
    }

    /**
     * Retry-After holds either delay seconds or an HTTP date.
     *
     * @param string $retryAfter
     * @param int    $now
     *
     * @return int|null
     */
    private function parseRetryAfter(string $retryAfter, int $now): ?int
    {
        if (ctype_digit($retryAfter)) {
            return $now + (int) $retryAfter;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::RFC1123, $retryAfter);

        return $date && $date->getTimestamp() > $now ? $date->getTimestamp() : null;
    }
}
