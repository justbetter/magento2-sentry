<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use JustBetter\Sentry\Model\PerformanceTracingDto;
use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\DB\LoggerInterface;
use Sentry\Tracing\SpanContext;

class DbQueryLoggerPlugin
{
    private ?PerformanceTracingDto $tracingDto = null;

    public function beforeStartTimer(LoggerInterface $subject): void
    {
        $this->tracingDto = SentryPerformance::traceStart(
            SpanContext::make()
                ->setOp('db.sql.query')
                ->setStartTimestamp(microtime(true))
        );
    }

    public function beforeLogStats(LoggerInterface $subject, $type, $sql, $bind = [], $result = null): void
    {
        if ($this->tracingDto === null) {
            return;
        }

        $this->tracingDto->getSpan()?->setDescription($sql);
        SentryPerformance::traceEnd($this->tracingDto);
        $this->tracingDto = null;
    }
}
