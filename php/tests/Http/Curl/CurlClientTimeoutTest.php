<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Http\Curl;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartTimeoutException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Http\Curl\CurlClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Tests\Integration\StubApiServer;

/**
 * Verifies that CurlClient distinguishes timeouts (CURLE_OPERATION_TIMEDOUT,
 * 28) from other transport failures so RetryingHttpClient (Task 12) can
 * retry timeouts without re-attempting non-retryable transport errors
 * like DNS resolution failures or TLS handshake errors.
 */
final class CurlClientTimeoutTest extends TestCase
{
    private ?StubApiServer $stub = null;

    protected function tearDown(): void
    {
        if ($this->stub !== null) {
            $this->stub->stop();
            $this->stub = null;
        }
    }

    public function test_send_throws_SpartTimeoutException_when_curl_times_out(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'late', 'delayMs' => 3_000],
        ]);

        $client = new CurlClient();
        $request = new HttpRequest(
            method: 'GET',
            url: $this->stub->url('/slow'),
            headers: [],
            body: null,
            timeoutSeconds: 1,
        );

        $this->expectException(SpartTimeoutException::class);
        $this->expectExceptionMessageMatches('/transport timeout/');
        $client->send($request);
    }

    public function test_SpartTimeoutException_is_caught_by_SpartTransportException_handlers(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'late', 'delayMs' => 3_000],
        ]);

        $client = new CurlClient();
        $request = new HttpRequest(
            method: 'GET',
            url: $this->stub->url('/slow'),
            headers: [],
            body: null,
            timeoutSeconds: 1,
        );

        try {
            $client->send($request);
            self::fail('Expected SpartTimeoutException');
        } catch (SpartTransportException $e) {
            self::assertInstanceOf(SpartTimeoutException::class, $e);
        }
    }
}
