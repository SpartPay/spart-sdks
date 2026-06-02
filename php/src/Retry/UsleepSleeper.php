<?php

declare(strict_types=1);

namespace Spart\Sdk\Retry;

/**
 * Default {@see Sleeper} implementation that delegates to PHP's
 * built-in `usleep`. Used by SpartClient's default factory wiring
 * when no test-specific Sleeper is injected.
 */
final class UsleepSleeper implements Sleeper
{
    public function sleepMs(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }
        usleep($ms * 1000);
    }
}
