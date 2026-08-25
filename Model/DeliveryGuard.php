<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

class DeliveryGuard
{
    /**
     * @var int Depth counter.
     */
    private int $depth = 0;

    /**
     * Mark that outbound Sentry delivery is in progress.
     */
    public function enter(): void
    {
        $this->depth++;
    }

    /**
     * Leave a matching enter() scope.
     */
    public function leave(): void
    {
        if ($this->depth > 0) {
            $this->depth--;
        }
    }

    /**
     * Whether a delivery attempt is already running in this process.
     */
    public function isActive(): bool
    {
        return $this->depth > 0;
    }
}
