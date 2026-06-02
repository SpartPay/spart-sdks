<?php

declare(strict_types=1);

namespace Spart\Sdk\Retry;

/**
 * Pause execution for a number of milliseconds. Abstracted so
 * RetryingHttpClient tests (and any consumer that wants deterministic
 * timing in tests) can swap a recording fake in place of the real
 * {@see UsleepSleeper} that actually blocks the calling thread.
 */
interface Sleeper
{
    public function sleepMs(int $ms): void;
}
