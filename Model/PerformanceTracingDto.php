<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use Sentry\State\Scope;
use Sentry\Tracing\Span;

class PerformanceTracingDto
{
    /**
     * PerformanceTracingDto constructor.
     *
     * @param Scope     $scope
     * @param Span|null $parentSpan
     * @param Span|null $span
     */
    public function __construct(
        private Scope $scope,
        private ?Span $parentSpan = null,
        private ?Span $span = null
    ) {
    }

    /**
     * Get scope.
     *
     * @return Scope
     */
    public function getScope(): Scope
    {
        return $this->scope;
    }

    /**
     * Get parent span.
     *
     * @return Span|null
     */
    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }

    /**
     * Get span.
     *
     * @return Span|null
     */
    public function getSpan(): ?Span
    {
        return $this->span;
    }
}
