<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use JustBetter\Sentry\Model\PerformanceTracingDto;
use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\DB\LoggerInterface;
use Sentry\Tracing\SpanContext;

class DbQueryLoggerPlugin
{
    /**
     * @var PerformanceTracingDto|null
     */
    private ?PerformanceTracingDto $tracingDto = null;

    /**
     * Starts a Sentry span.
     *
     * @param LoggerInterface $subject
     *
     * @return void
     */
    public function beforeStartTimer(LoggerInterface $subject): void
    {
        $this->tracingDto = SentryPerformance::traceStart(
            SpanContext::make()
                ->setOp('db.sql.query')
                ->setStartTimestamp(microtime(true))
        );
    }

    /**
     * Stops the previously create span (span created in `beforeStartTimer`).
     *
     * @param LoggerInterface $subject
     * @param string          $type
     * @param string          $sql
     * @param array           $bind
     * @param mixed           $result
     *
     * @return void
     */
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
