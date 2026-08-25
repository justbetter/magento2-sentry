<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Cache-backed circuit breaker for outbound Sentry HTTP calls.
 *
 * States: closed (normal) → open (fail fast) → half-open (probe) → closed.
 */
class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    private const CACHE_KEY = 'justbetter_sentry_circuit_breaker';
    private const CACHE_TAG = 'JUSTBETTER_SENTRY_CIRCUIT_BREAKER';

    /**
     * @var array{state:string,failures:int,successes:int,opened_at:float}|null
     */
    private ?array $state = null;

    /**
     * @param CacheInterface $cache
     * @param Json           $serializer
     * @param Data           $helper
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $serializer,
        private readonly Data $helper
    ) {
    }

    /**
     * Whether a sync HTTP attempt is allowed.
     */
    public function allowRequest(): bool
    {
        if (!$this->helper->isCircuitBreakerEnabled()) {
            return true;
        }

        $state = $this->getState();

        return match ($state['state']) {
            self::STATE_CLOSED, self::STATE_HALF_OPEN => true,
            self::STATE_OPEN => $this->tryTransitionToHalfOpen($state),
            default          => true,
        };
    }

    /**
     * Record a successful Sentry HTTP delivery.
     */
    public function recordSuccess(): void
    {
        if (!$this->helper->isCircuitBreakerEnabled()) {
            return;
        }

        $state = $this->getState();

        if ($state['state'] === self::STATE_HALF_OPEN) {
            $state['successes']++;
            if ($state['successes'] >= $this->helper->getCircuitBreakerSuccessThreshold()) {
                $this->resetToClosed();

                return;
            }
            $this->persist($state);

            return;
        }

        if ($state['failures'] !== 0 || $state['state'] !== self::STATE_CLOSED) {
            $this->resetToClosed();
        }
    }

    /**
     * Record a failed Sentry HTTP delivery.
     */
    public function recordFailure(): void
    {
        if (!$this->helper->isCircuitBreakerEnabled()) {
            return;
        }

        $state = $this->getState();

        if ($state['state'] === self::STATE_HALF_OPEN) {
            $this->openCircuit($this->helper->getCircuitBreakerFailureThreshold());

            return;
        }

        $state['failures']++;
        $state['successes'] = 0;

        if ($state['failures'] >= $this->helper->getCircuitBreakerFailureThreshold()) {
            $this->openCircuit($state['failures']);

            return;
        }

        $this->persist($state);
    }

    /**
     * Load circuit breaker state from in-memory cache or Magento cache.
     *
     * @return array{state:string,failures:int,successes:int,opened_at:float}
     */
    private function getState(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $cached = $this->cache->load(self::CACHE_KEY);
        if (!is_string($cached) || $cached === '') {
            return $this->state = $this->closedState();
        }

        try {
            $decoded = $this->serializer->unserialize($cached);
        } catch (\InvalidArgumentException) {
            return $this->state = $this->closedState();
        }

        if (!is_array($decoded)) {
            return $this->state = $this->closedState();
        }

        return $this->state = [
            'state'     => (string) ($decoded['state'] ?? self::STATE_CLOSED),
            'failures'  => (int) ($decoded['failures'] ?? 0),
            'successes' => (int) ($decoded['successes'] ?? 0),
            'opened_at' => (float) ($decoded['opened_at'] ?? 0.0),
        ];
    }

    /**
     * Transition from open to half-open after the recovery timeout.
     *
     * @param array{state:string,failures:int,successes:int,opened_at:float} $state
     */
    private function tryTransitionToHalfOpen(array $state): bool
    {
        $recoveryTimeout = $this->helper->getCircuitBreakerRecoveryTimeout();
        if ($state['opened_at'] <= 0
            || (microtime(true) - $state['opened_at']) < $recoveryTimeout
        ) {
            return false;
        }

        $this->persist([
            'state'     => self::STATE_HALF_OPEN,
            'failures'  => $state['failures'],
            'successes' => 0,
            'opened_at' => $state['opened_at'],
        ]);

        return true;
    }

    /**
     * Default closed circuit state.
     *
     * @return array{state:string,failures:int,successes:int,opened_at:float}
     */
    private function closedState(): array
    {
        return [
            'state'     => self::STATE_CLOSED,
            'failures'  => 0,
            'successes' => 0,
            'opened_at' => 0.0,
        ];
    }

    /**
     * Reset circuit to closed and clear counters.
     */
    private function resetToClosed(): void
    {
        $this->persist($this->closedState());
    }

    /**
     * Open the circuit after failure threshold is reached.
     *
     * @param int $failures Current failure count
     */
    private function openCircuit(int $failures): void
    {
        $this->persist([
            'state'     => self::STATE_OPEN,
            'failures'  => $failures,
            'successes' => 0,
            'opened_at' => microtime(true),
        ]);
    }

    /**
     * Persist circuit state to memory and Magento cache.
     *
     * @param array{state:string,failures:int,successes:int,opened_at:float} $state
     */
    private function persist(array $state): void
    {
        $this->state = $state;
        // Keep state longer than recovery window so open state survives across requests.
        $ttl = max(300, $this->helper->getCircuitBreakerRecoveryTimeout() * 5);
        $this->cache->save(
            (string) $this->serializer->serialize($state),
            self::CACHE_KEY,
            [self::CACHE_TAG],
            $ttl
        );
    }
}
