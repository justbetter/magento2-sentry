<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use Sentry\State\Scope;
use Sentry\Tracing\Span;

class PerformanceTracingDto
{
    public function __construct(
        private Scope $scope,
        private ?Span $parentSpan = null,
        private ?Span $span = null
    ) {
    }

    public function getScope(): Scope
    {
        return $this->scope;
    }

    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }

    public function getSpan(): ?Span
    {
        return $this->span;
    }
}
