<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartRateLimitException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Exceptions\SpartTimeoutException;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Http\RetryingHttpClient;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\Retry\Sleeper;

final class RetryingHttpClientTest extends TestCase
{
    private function request(): HttpRequest
    {
        return new HttpRequest(method: 'GET', url: 'https://example.test/probe');
    }

    public function test_retries_until_success_on_transient_errors(): void
    {
        $ok = new HttpResponse(200, [], '{}');
        $inner = new ScriptedHttpClient([
            new SpartTimeoutException('t1'),
            new SpartServerException('boom', 503),
            $ok,
        ]);
        $sleeper = new RecordingSleeper();
        $policy = new RetryPolicy(maxRetries: 3, baseDelayMs: 10, maxDelayMs: 100, jitter: false);

        $response = (new RetryingHttpClient($inner, $policy, $sleeper))->send($this->request());

        self::assertSame(200, $response->statusCode);
        self::assertSame(3, $inner->callCount);
        self::assertCount(2, $sleeper->calls);
        self::assertSame(10, $sleeper->calls[0]);
        self::assertSame(20, $sleeper->calls[1]);
    }

    public function test_does_not_retry_4xx_client_errors(): void
    {
        $inner = new ScriptedHttpClient([new SpartApiException('bad', 400)]);
        $sleeper = new NullSleeper();
        $client = new RetryingHttpClient($inner, RetryPolicy::default(), $sleeper);

        try {
            $client->send($this->request());
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertSame(400, $e->statusCode);
        } finally {
            self::assertSame(1, $inner->callCount);
        }
    }

    public function test_retries_429_RateLimit_with_RetryAfter_when_present(): void
    {
        // This test verifies that the Retry-After value is HONORED (i.e. the
        // sleep is at least that long). The DIRECTION of the max() — that
        // Retry-After dominates the policy delay when larger, and the policy
        // delay dominates when Retry-After is smaller — is pinned by
        // test_uses_policy_delay_when_retry_after_smaller below; that's the
        // load-bearing direction guard.
        $ok = new HttpResponse(200, [], '{}');
        $inner = new ScriptedHttpClient([
            new SpartRateLimitException('rate-limited', retryAfterSeconds: 1),
            $ok,
        ]);
        $sleeper = new RecordingSleeper();
        $client = new RetryingHttpClient($inner, RetryPolicy::default(), $sleeper);

        $response = $client->send($this->request());

        self::assertSame(200, $response->statusCode);
        self::assertCount(1, $sleeper->calls);
        self::assertGreaterThanOrEqual(1000, $sleeper->calls[0]);
    }

    public function test_giving_up_rethrows_last_exception(): void
    {
        $inner = new ScriptedHttpClient([
            new SpartServerException('e1', 503),
            new SpartServerException('e2', 503),
            new SpartServerException('e3', 503),
        ]);
        $policy = new RetryPolicy(maxRetries: 2, baseDelayMs: 1, maxDelayMs: 10, jitter: false);
        $client = new RetryingHttpClient($inner, $policy, new NullSleeper());

        try {
            $client->send($this->request());
            $this->fail('expected SpartServerException');
        } catch (SpartServerException $e) {
            self::assertSame('e3', $e->getMessage());
            self::assertSame(3, $inner->callCount);
        }
    }

    public function test_does_not_retry_when_max_retries_zero(): void
    {
        $inner = new ScriptedHttpClient([new SpartTimeoutException('t1')]);
        $sleeper = new RecordingSleeper();
        $client = new RetryingHttpClient($inner, RetryPolicy::none(), $sleeper);

        try {
            $client->send($this->request());
            $this->fail('expected SpartTimeoutException');
        } catch (SpartTimeoutException $e) {
            self::assertSame('t1', $e->getMessage());
        } finally {
            self::assertSame(1, $inner->callCount);
            self::assertSame([], $sleeper->calls);
        }
    }

    public function test_uses_policy_delay_when_retry_after_smaller(): void
    {
        $ok = new HttpResponse(200, [], '{}');
        $inner = new ScriptedHttpClient([
            new SpartRateLimitException('rate-limited', retryAfterSeconds: 0),
            $ok,
        ]);
        $sleeper = new RecordingSleeper();
        $policy = new RetryPolicy(maxRetries: 3, baseDelayMs: 200, maxDelayMs: 1000, jitter: false);

        $response = (new RetryingHttpClient($inner, $policy, $sleeper))->send($this->request());

        self::assertSame(200, $response->statusCode);
        self::assertSame([200], $sleeper->calls);
    }

    public function test_clamps_excessive_retry_after_to_policy_maxDelayMs(): void
    {
        // A hostile or buggy server returning Retry-After: 999999 must NOT
        // be allowed to block the calling thread for 11+ days. The policy's
        // maxDelayMs is the hard cap on any single sleep.
        $ok = new HttpResponse(200, [], '{}');
        $inner = new ScriptedHttpClient([
            new SpartRateLimitException('rate-limited', retryAfterSeconds: 999999),
            $ok,
        ]);
        $sleeper = new RecordingSleeper();
        $policy = new RetryPolicy(maxRetries: 3, baseDelayMs: 200, maxDelayMs: 1000, jitter: false);

        $response = (new RetryingHttpClient($inner, $policy, $sleeper))->send($this->request());

        self::assertSame(200, $response->statusCode);
        self::assertSame([1000], $sleeper->calls);
    }

    public function test_does_not_retry_unrelated_exceptions(): void
    {
        $inner = new ScriptedHttpClient([new \LogicException('boom')]);
        $sleeper = new NullSleeper();
        $client = new RetryingHttpClient($inner, RetryPolicy::default(), $sleeper);

        try {
            $client->send($this->request());
            $this->fail('expected LogicException');
        } catch (\LogicException $e) {
            self::assertSame('boom', $e->getMessage());
        } finally {
            self::assertSame(1, $inner->callCount);
        }
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// Test doubles colocated with the test for readability; one-shot fakes do
// not warrant their own files.

final class ScriptedHttpClient implements HttpClient
{
    public int $callCount = 0;

    /** @param list<HttpResponse|\Throwable> $script */
    public function __construct(private array $script)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $step = $this->script[$this->callCount] ?? throw new \LogicException('script exhausted');
        $this->callCount++;
        if ($step instanceof \Throwable) {
            throw $step;
        }
        return $step;
    }
}

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $calls = [];

    public function sleepMs(int $ms): void
    {
        $this->calls[] = $ms;
    }
}

final class NullSleeper implements Sleeper
{
    public function sleepMs(int $ms): void
    {
    }
}
