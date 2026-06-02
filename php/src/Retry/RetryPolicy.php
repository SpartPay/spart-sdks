<?php

declare(strict_types=1);

namespace Spart\Sdk\Retry;

/**
 * Immutable value object describing how RetryingHttpClient should
 * back off and retry transient failures (5xx, timeouts, throttled
 * requests).
 *
 * Backoff is full-jitter exponential per the AWS Architecture Blog
 * recommendation: each retry's delay is randomly picked in
 * [0, min(maxDelayMs, baseDelayMs * 2^(attempt-1))]. This avoids the
 * thundering-herd retry storm a deterministic exponential schedule
 * causes when many clients fail simultaneously.
 *
 * Attempt numbering: `attempt` is 1-based — `attempt=1` is the first
 * RETRY (not the original request). For maxRetries=3 there will be
 * up to 3 retry sleeps, after attempts 1, 2, 3.
 */
final class RetryPolicy
{
    public function __construct(
        public readonly int $maxRetries,
        public readonly int $baseDelayMs,
        public readonly int $maxDelayMs,
        public readonly bool $jitter,
    ) {
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('maxRetries must be >= 0.');
        }
        if ($baseDelayMs < 0 || $maxDelayMs < 0) {
            throw new \InvalidArgumentException('baseDelayMs and maxDelayMs must be >= 0.');
        }
        if ($baseDelayMs > $maxDelayMs) {
            throw new \InvalidArgumentException('baseDelayMs must be <= maxDelayMs.');
        }
    }

    /**
     * Sensible default: 3 retries, 200ms base, 5s ceiling, full jitter.
     * Total worst-case delay (no jitter): 200 + 400 + 800 = 1.4s before
     * giving up on a 5xx burst.
     */
    public static function default(): self
    {
        return new self(maxRetries: 3, baseDelayMs: 200, maxDelayMs: 5_000, jitter: true);
    }

    /** Disables retries entirely; equivalent to wrapping the inner client unchanged. */
    public static function none(): self
    {
        return new self(maxRetries: 0, baseDelayMs: 0, maxDelayMs: 0, jitter: false);
    }

    /**
     * Returns the delay in milliseconds to wait before the given retry
     * attempt (1-based). Capped at maxDelayMs; with jitter enabled the
     * actual wait is uniformly random in [0, capped exponential].
     *
     * @param int $attempt 1-based retry attempt number (1 = first retry)
     */
    public function delayMsForAttempt(int $attempt): int
    {
        if ($attempt < 1) {
            throw new \InvalidArgumentException('attempt must be >= 1.');
        }

        $exp = (int) min($this->maxDelayMs, $this->baseDelayMs * (2 ** ($attempt - 1)));
        if ($this->jitter && $exp > 0) {
            return random_int(0, $exp);
        }

        return $exp;
    }
}
