<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use Magento\Framework\View\Element\Template;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

class TemplatePlugin
{
    private const SPAN_STORAGE_KEY = '__sentry_profiling_span_fetch_view';

    public function beforeFetchView(Template $subject, $fileName): void
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        if (!$parentSpan) {
            return;
        }

        $context = SpanContext::make()
            ->setOp('template.render')
            ->setDescription($subject->getNameInLayout() ?: $fileName)
            ->setData([
                'block_name'  => $subject->getNameInLayout(),
                'block_class' => get_class($subject),
                'module'      => $subject->getModuleName(),
                'template'    => $fileName,
            ]);
        $span = $parentSpan->startChild($context);

        SentrySdk::getCurrentHub()->setSpan($span);

        $subject->setData(self::SPAN_STORAGE_KEY, [$span, $parentSpan]);
    }

    public function afterFetchView(Template $subject, $result, $fileName)
    {
        $span = $subject->getData(self::SPAN_STORAGE_KEY) ?: [];

        if ($span[0] instanceof Span) {
            $span[0]->finish();

            SentrySdk::getCurrentHub()->setSpan($span[1]);
        }

        return $result;
    }
}
