<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Http\Curl\CurlHttpClientFactory;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\Retry\Sleeper;
use Spart\Sdk\SpartClient;
use Spart\Sdk\SpartClientConfig;
use Spart\Sdk\Tests\Integration\Fixtures\IntentRequestFixture;
use Spart\Sdk\Tests\Integration\Fixtures\IntentResponseFixture;

/**
 * Integration retry-pipeline coverage.
 *
 * Drives the full SDK stack (CurlHttpClientFactory → RetryingHttpClient
 * → CurlClient) against the {@see StubApiServer} fixture so we can
 * script exact failure sequences (503 → 503 → 200, 4 × 503 → exhausted,
 * 400 → no-retry). There is no real API server in the loop — the SDK is
 * exercised against a local `php -S` stub over a real socket with a real
 * cURL transport, which is why it lives in `tests/Integration/`.
 *
 * A no-op {@see Sleeper} is injected explicitly so retry sleeps don't
 * inflate the test wall-clock; the StubApiServer never `delayMs`s
 * either.
 *
 * Note: this fixture is single-client and the cursor file is read-modify-
 * written without locking — see the StubApiServer docblock — so the
 * tests issue strictly sequential requests.
 */
final class RetryIntegrationTest extends TestCase
{
    public function test_retries_503_then_succeeds(): void
    {
        $stub = StubApiServer::start([
            ['status' => 503, 'body' => '{"error":"unavailable"}'],
            ['status' => 503, 'body' => '{"error":"unavailable"}'],
            ['status' => 201, 'body' => (string) json_encode(IntentResponseFixture::createOk(), JSON_THROW_ON_ERROR)],
        ]);
        try {
            $client = $this->buildClient($stub->url(''), maxRetries: 3);
            $resp = $client->intents()->create(IntentRequestFixture::valid());
            self::assertSame('abc123', $resp->intentShortId);
        } finally {
            $stub->stop();
        }
    }

    public function test_does_not_retry_400_validation_failure(): void
    {
        $stub = StubApiServer::start([
            ['status' => 400, 'body' => (string) json_encode(IntentResponseFixture::failure('validation.failed', 'bad', ['x']), JSON_THROW_ON_ERROR)],
            // A second scripted response would be available IF the SDK
            // (incorrectly) retried; we assert exhaustion never happens.
            ['status' => 201, 'body' => (string) json_encode(IntentResponseFixture::createOk(), JSON_THROW_ON_ERROR)],
        ]);
        try {
            $this->expectException(SpartApiException::class);
            $this->buildClient($stub->url(''), maxRetries: 3)->intents()->create(IntentRequestFixture::valid());
        } finally {
            $stub->stop();
        }
    }

    public function test_gives_up_after_max_retries(): void
    {
        $stub = StubApiServer::start(array_fill(0, 5, ['status' => 503, 'body' => '{"error":"x"}']));
        try {
            $this->expectException(SpartServerException::class);
            $this->buildClient($stub->url(''), maxRetries: 2)
                ->intents()
                ->create(IntentRequestFixture::valid());
        } finally {
            $stub->stop();
        }
    }

    public function test_disable_retry_propagates_first_5xx(): void
    {
        $stub = StubApiServer::start([['status' => 503, 'body' => '{"error":"x"}']]);
        try {
            $this->expectException(SpartServerException::class);
            $this->buildClient($stub->url(''), maxRetries: 0)
                ->intents()
                ->create(IntentRequestFixture::valid());
        } finally {
            $stub->stop();
        }
    }

    private function buildClient(string $baseUrl, int $maxRetries): SpartClient
    {
        $cfg = new SpartClientConfig(
            baseUrl: $baseUrl,
            apiKey: 'sk_test_dummy',
            timeoutSeconds: 5,
            retryPolicy: new RetryPolicy(
                maxRetries: $maxRetries,
                baseDelayMs: 1,
                maxDelayMs: 5,
                jitter: false,
            ),
        );

        // Inject a no-op sleeper so retry waits don't bloat wall-clock.
        $factory = new CurlHttpClientFactory(
            policy: $cfg->retryPolicy,
            sleeper: new class implements Sleeper {
                public function sleepMs(int $ms): void
                {
                }
            },
        );

        return new SpartClient($cfg, $factory);
    }
}
