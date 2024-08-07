<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use Magento\Framework\Event\ConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

class EventManagerPlugin
{
    private const SPAN_STORAGE_KEY = '__sentry_profiling_span';

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
        ], $excludePatterns);
    }

    public function beforeDispatch(ManagerInterface $subject, string|null $eventName, array $data = []): array
    {
        if ($eventName === null) {
            return [$eventName, $data];
        }

        $parent = SentrySdk::getCurrentHub()->getSpan();
        if ($parent === null) {
            // can happen if no transaction has been started
            return [$eventName, $data];
        }

        foreach ($this->excludePatterns as $excludePattern) {
            if (preg_match('/'.$excludePattern.'/i', $eventName)) {
                return [$eventName, $data];
            }
        }

        if ($this->config->getObservers(mb_strtolower($eventName)) === []) {
            return [$eventName, $data];
        }

        $context = SpanContext::make()
            ->setOp('event')
            ->setDescription($eventName)
            ->setData([
                'event.name' => $eventName,
            ]);

        $span = $parent->startChild($context);
        SentrySdk::getCurrentHub()->setSpan($span);
        $data[self::SPAN_STORAGE_KEY] = [$span, $parent];

        return [$eventName, $data];
    }

    public function afterDispatch(ManagerInterface $subject, $result, string $name, array $data = []): void
    {
        /** @var Span[]|null $span */
        $span = $data[self::SPAN_STORAGE_KEY] ?? null;
        if (!is_array($span)) {
            return;
        }

        if (isset($span[0])) {
            $span[0]->finish();

            SentrySdk::getCurrentHub()->setSpan($span[1]);
        }
    }
}
