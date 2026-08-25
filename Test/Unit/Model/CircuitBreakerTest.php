<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\CircuitBreaker;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    /**
     * @var CacheInterface&Stub
     */
    private $cache;

    /**
     * @var Data&Stub
     */
    private $helper;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @var array<string, string>
     */
    private array $storage = [];

    protected function setUp(): void
    {
        $this->storage = [];
        $this->serializer = new Json();
        $this->helper = $this->createStub(Data::class);
        $this->cache = $this->createStub(CacheInterface::class);

        $this->cache->method('load')->willReturnCallback(
            function (string $key): string|false {
                if (!array_key_exists($key, $this->storage)) {
                    return false;
                }

                return $this->storage[$key];
            }
        );
        $this->cache->method('save')->willReturnCallback(
            function (string $data, string $key): bool {
                $this->storage[$key] = $data;

                return true;
            }
        );

        $this->helper->method('isCircuitBreakerEnabled')->willReturn(true);
        $this->helper->method('getCircuitBreakerFailureThreshold')->willReturn(3);
        $this->helper->method('getCircuitBreakerSuccessThreshold')->willReturn(2);
        $this->helper->method('getCircuitBreakerRecoveryTimeout')->willReturn(60);
    }

    private function createBreaker(
        ?CacheInterface $cache = null,
        ?Data $helper = null
    ): CircuitBreaker {
        return new CircuitBreaker(
            $cache ?? $this->cache,
            $this->serializer,
            $helper ?? $this->helper
        );
    }

    public function testAllowRequestWhenDisabled(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isCircuitBreakerEnabled')->willReturn(false);

        $this->assertTrue($this->createBreaker(helper: $helper)->allowRequest());
    }

    public function testStartsClosedAndAllowsRequests(): void
    {
        $this->assertTrue($this->createBreaker()->allowRequest());
    }

    public function testOpensAfterFailureThreshold(): void
    {
        $breaker = $this->createBreaker();

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertTrue($breaker->allowRequest());

        $breaker->recordFailure();
        $this->assertFalse($breaker->allowRequest());
    }

    public function testRecordSuccessResetsFailuresWhileClosed(): void
    {
        $breaker = $this->createBreaker();

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertTrue($breaker->allowRequest());
    }

    public function testHalfOpenAfterRecoveryTimeoutThenClosesOnSuccessThreshold(): void
    {
        $this->storage['justbetter_sentry_circuit_breaker'] = (string) $this->serializer->serialize([
            'state'     => CircuitBreaker::STATE_OPEN,
            'failures'  => 3,
            'successes' => 0,
            'opened_at' => microtime(true) - 120,
        ]);

        $breaker = $this->createBreaker();

        $this->assertTrue($breaker->allowRequest());

        $breaker->recordSuccess();
        $breaker->recordSuccess();

        $state = $this->serializer->unserialize($this->storage['justbetter_sentry_circuit_breaker']);
        $this->assertIsArray($state);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $state['state']);
        $this->assertSame(0, $state['failures']);
    }

    public function testHalfOpenFailureReopensCircuit(): void
    {
        $this->storage['justbetter_sentry_circuit_breaker'] = (string) $this->serializer->serialize([
            'state'     => CircuitBreaker::STATE_HALF_OPEN,
            'failures'  => 3,
            'successes' => 0,
            'opened_at' => microtime(true) - 120,
        ]);

        $breaker = $this->createBreaker();
        $breaker->recordFailure();

        $state = $this->serializer->unserialize($this->storage['justbetter_sentry_circuit_breaker']);
        $this->assertIsArray($state);
        $this->assertSame(CircuitBreaker::STATE_OPEN, $state['state']);
        $this->assertFalse($breaker->allowRequest());
    }

    public function testRecordSuccessAndFailureNoopWhenDisabled(): void
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isCircuitBreakerEnabled')->willReturn(false);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('save');

        $breaker = $this->createBreaker($cache, $helper);
        $breaker->recordSuccess();
        $breaker->recordFailure();
    }

    public function testCorruptCacheFallsBackToClosed(): void
    {
        $this->storage['justbetter_sentry_circuit_breaker'] = 'not-json';

        $this->assertTrue($this->createBreaker()->allowRequest());
    }
}
