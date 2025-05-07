<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\Event\ConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Sentry\Tracing\SpanContext;

class EventManagerPlugin
{
    /**
     * EventManagerPlugin constructor.
     *
     * @param ConfigInterface $config
     * @param array           $excludePatterns
     */
    public function __construct(
        private ConfigInterface $config,
        private array $excludePatterns = []
    ) {
        $this->excludePatterns = array_merge([
            '^model_load_',
            '_load_before$',
            '_load_after$',
            '_$',
            '^view_block_abstract_',
            '^core_layout_render_e',
        ], $excludePatterns);
    }

    /**
     * Method checks if the block is excluded for php profiling.
     *
     * @param string|null $eventName
     *
     * @return bool
     */
    private function _canTrace(?string $eventName): bool
    {
        if ($eventName === null) {
            return false;
        }

        foreach ($this->excludePatterns as $excludePattern) {
            if (preg_match('/'.$excludePattern.'/i', $eventName)) {
                return false;
            }
        }

        if ($this->config->getObservers(mb_strtolower($eventName)) === []) {
            return false;
        }

        return true;
    }

    /**
     * Method creates a Sentry span for php profiling to profile event handling.
     *
     * @param ManagerInterface $subject
     * @param callable         $callable
     * @param string           $eventName
     * @param array            $data
     *
     * @return mixed
     */
    public function aroundDispatch(ManagerInterface $subject, callable $callable, string $eventName, array $data = []): mixed
    {
        if (!$this->_canTrace($eventName)) {
            return $callable($eventName, $data);
        }

        $context = SpanContext::make()
            ->setOp('event')
            ->setDescription($eventName)
            ->setData([
                'event.name' => $eventName,
            ]);

        $tracingDto = SentryPerformance::traceStart($context);

        try {
            return $callable($eventName, $data);
        } finally {
            SentryPerformance::traceEnd($tracingDto);
        }
    }
}
