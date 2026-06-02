<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Retry;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Retry\RetryPolicy;

final class RetryPolicyTest extends TestCase
{
    public function test_default_returns_3_retries_with_jitter(): void
    {
        $p = RetryPolicy::default();

        self::assertSame(3, $p->maxRetries);
        self::assertGreaterThan(0, $p->baseDelayMs);
        self::assertGreaterThanOrEqual($p->baseDelayMs, $p->maxDelayMs);
        self::assertTrue($p->jitter);
    }

    public function test_none_disables_retries(): void
    {
        $p = RetryPolicy::none();

        self::assertSame(0, $p->maxRetries);
        self::assertSame(0, $p->baseDelayMs);
        self::assertSame(0, $p->maxDelayMs);
        self::assertFalse($p->jitter);
    }

    public function test_constructor_rejects_negative_retries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(maxRetries: -1, baseDelayMs: 100, maxDelayMs: 1000, jitter: false);
    }

    public function test_constructor_rejects_negative_base_delay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(maxRetries: 3, baseDelayMs: -1, maxDelayMs: 1000, jitter: false);
    }

    public function test_constructor_rejects_negative_max_delay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(maxRetries: 3, baseDelayMs: 100, maxDelayMs: -1, jitter: false);
    }

    public function test_constructor_rejects_baseDelay_greater_than_maxDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(maxRetries: 3, baseDelayMs: 5000, maxDelayMs: 1000, jitter: false);
    }

    public function test_delayMsForAttempt_doubles_without_jitter(): void
    {
        $p = new RetryPolicy(maxRetries: 5, baseDelayMs: 100, maxDelayMs: 10_000, jitter: false);

        self::assertSame(100, $p->delayMsForAttempt(1));
        self::assertSame(200, $p->delayMsForAttempt(2));
        self::assertSame(400, $p->delayMsForAttempt(3));
        self::assertSame(800, $p->delayMsForAttempt(4));
    }

    public function test_delayMsForAttempt_clamps_to_maxDelay(): void
    {
        $p = new RetryPolicy(maxRetries: 10, baseDelayMs: 100, maxDelayMs: 250, jitter: false);

        // 100, 200, 400 -> clamped to 250
        self::assertSame(100, $p->delayMsForAttempt(1));
        self::assertSame(200, $p->delayMsForAttempt(2));
        self::assertSame(250, $p->delayMsForAttempt(3));
        self::assertSame(250, $p->delayMsForAttempt(8));
    }

    public function test_delayMsForAttempt_with_jitter_returns_value_in_bounded_range(): void
    {
        $p = new RetryPolicy(maxRetries: 5, baseDelayMs: 100, maxDelayMs: 10_000, jitter: true);

        // Attempt 3 -> exponential cap is 400; jitter picks in [0, 400].
        for ($i = 0; $i < 50; $i++) {
            $delay = $p->delayMsForAttempt(3);
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(400, $delay);
        }
    }

    public function test_delayMsForAttempt_rejects_zero_or_negative_attempt(): void
    {
        $p = RetryPolicy::default();

        $this->expectException(\InvalidArgumentException::class);
        $p->delayMsForAttempt(0);
    }
}
