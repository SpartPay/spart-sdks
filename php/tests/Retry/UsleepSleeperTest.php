<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Retry;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Retry\UsleepSleeper;

final class UsleepSleeperTest extends TestCase
{
    public function test_sleepMs_returns_immediately_for_zero_or_negative(): void
    {
        $sleeper = new UsleepSleeper();

        $start = microtime(true);
        $sleeper->sleepMs(0);
        $sleeper->sleepMs(-100);
        $elapsedMs = (microtime(true) - $start) * 1000;

        // Should be near-instantaneous; allow 50ms slack for slow CI.
        self::assertLessThan(50, $elapsedMs);
    }

    public function test_sleepMs_actually_sleeps_for_positive_durations(): void
    {
        $sleeper = new UsleepSleeper();

        $start = microtime(true);
        $sleeper->sleepMs(50);
        $elapsedMs = (microtime(true) - $start) * 1000;

        // Allow generous tolerance for CI scheduler jitter; just confirm
        // at least the requested delay actually elapsed.
        self::assertGreaterThanOrEqual(45, $elapsedMs);
    }
}
