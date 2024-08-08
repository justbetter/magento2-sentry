<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

use JustBetter\Sentry\Model\SentryPerformance;
use Magento\Framework\View\Element\Template;
use Sentry\Tracing\SpanContext;

class TemplatePlugin
{
    public function aroundFetchView(Template $subject, callable $callable, $fileName): mixed
    {
        $tags = [];
        if (!empty($subject->getModuleName())) {
            $tags['magento.module'] = $subject->getModuleName();
        }

        $context = SpanContext::make()
            ->setOp('template.render')
            ->setDescription($subject->getNameInLayout() ?: $fileName)
            ->setTags($tags)
            ->setData([
                'block_name'  => $subject->getNameInLayout(),
                'block_class' => get_class($subject),
                'module'      => $subject->getModuleName(),
                'template'    => $fileName,
            ]);

        $tracingDto = SentryPerformance::traceStart($context);

        try {
            return $callable($fileName);
        } finally {
            SentryPerformance::traceEnd($tracingDto);
        }
    }
}
