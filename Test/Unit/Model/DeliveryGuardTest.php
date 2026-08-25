<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Test\Unit\Model;

use JustBetter\Sentry\Model\DeliveryGuard;
use PHPUnit\Framework\TestCase;

class DeliveryGuardTest extends TestCase
{
    public function testStartsInactive(): void
    {
        $this->assertFalse((new DeliveryGuard())->isActive());
    }

    public function testEnterActivatesAndLeaveDeactivates(): void
    {
        $guard = new DeliveryGuard();

        $guard->enter();
        $this->assertTrue($guard->isActive());

        $guard->leave();
        $this->assertFalse($guard->isActive());
    }

    public function testNestedEnterStaysActiveUntilMatchingLeave(): void
    {
        $guard = new DeliveryGuard();

        $guard->enter();
        $guard->enter();
        $guard->leave();
        $this->assertTrue($guard->isActive());

        $guard->leave();
        $this->assertFalse($guard->isActive());
    }

    public function testLeaveOnInactiveGuardIsSafe(): void
    {
        $guard = new DeliveryGuard();
        $guard->leave();

        $this->assertFalse($guard->isActive());
    }
}
