<?php

declare(strict_types=1);

namespace Spart\Sdk\Http;

use Spart\Sdk\Exceptions\SpartRateLimitException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\Retry\Sleeper;

/**
 * Decorator {@see HttpClient} that retries requests classified as transient
 * failures using the schedule defined by the injected {@see RetryPolicy}.
 *
 * Retry classification:
 *  - {@see SpartTransportException} (and its subclass {@see \Spart\Sdk\Exceptions\SpartTimeoutException}):
 *    DNS, TCP, TLS, connection-reset and timeout failures are intrinsically
 *    safe to retry because the server is presumed not to have observed the
 *    request (or its observation is uncertain — idempotency keys cover the
 *    "uncertain" case at the application layer).
 *  - {@see SpartServerException} (HTTP 5xx): the server explicitly told us
 *    it failed; retrying may succeed once the upstream issue clears.
 *  - {@see SpartRateLimitException} (HTTP 429): the server told us to back
 *    off. We honour the server-provided `Retry-After` value when it is
 *    larger than the policy's exponential backoff for this attempt.
 *
 * 4xx responses (validation, auth, conflict — anything modelled by
 * {@see \Spart\Sdk\Exceptions\SpartApiException} that is NOT a 5xx or 429)
 * are NOT retried because the failure is caused by the request itself and
 * resending the same payload will produce the same failure.
 *
 * `RetryPolicy::maxRetries` counts retries, not attempts: a value of 3
 * permits up to 4 total `send()` calls (1 initial + 3 retries). When the
 * retry budget is exhausted the LAST observed exception is re-thrown so
 * callers see the most recent failure cause.
 */
final class RetryingHttpClient implements HttpClient
{
    public function __construct(
        private readonly HttpClient $inner,
        private readonly RetryPolicy $policy,
        private readonly Sleeper $sleeper,
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $attempt = 0;
        while (true) {
            try {
                return $this->inner->send($request);
            } catch (SpartTransportException | SpartServerException | SpartRateLimitException $e) {
                $attempt++;
                if ($attempt > $this->policy->maxRetries) {
                    throw $e;
                }
                $this->sleeper->sleepMs($this->delayMsFor($attempt, $e));
            }
        }
    }

    private function delayMsFor(int $attempt, SpartTransportException|SpartServerException|SpartRateLimitException $e): int
    {
        $delayMs = $this->policy->delayMsForAttempt($attempt);
        if ($e instanceof SpartRateLimitException && $e->retryAfterSeconds !== null) {
            // Honour the server's Retry-After when it asks for MORE than the
            // policy's default backoff, but never exceed maxDelayMs — a
            // hostile or buggy server returning Retry-After: 999999 must
            // not be allowed to block the calling thread for 11+ days.
            $delayMs = min(max($delayMs, $e->retryAfterSeconds * 1000), $this->policy->maxDelayMs);
        }
        return $delayMs;
    }
}
