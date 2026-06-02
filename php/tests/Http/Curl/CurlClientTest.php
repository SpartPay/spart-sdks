<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Http\Curl;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Http\Curl\CurlClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Tests\Integration\StubApiServer;

/**
 * End-to-end verification that CurlClient sets the User-Agent header on
 * outbound HTTP requests. Pairs with CurlHttpClientFactoryTest's
 * reflection check (which proves the wiring) by going one level deeper
 * and confirming the wire actually carries the configured UA — so a
 * future refactor that drops CURLOPT_USERAGENT would be caught here.
 *
 * Uses StubApiServer, which echoes the inbound User-Agent back in the
 * X-Echo-User-Agent response header so any test (not just this one) can
 * assert on what the SDK actually sent.
 */
final class CurlClientTest extends TestCase
{
    private ?StubApiServer $stub = null;

    protected function tearDown(): void
    {
        if ($this->stub !== null) {
            $this->stub->stop();
            $this->stub = null;
        }
    }

    public function test_send_uses_default_user_agent_when_none_provided(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'ok'],
        ]);

        $client = new CurlClient();
        $response = $client->send(new HttpRequest(
            method: 'GET',
            url: $this->stub->url('/x'),
            headers: [],
            body: null,
            timeoutSeconds: 5,
        ));

        self::assertSame(200, $response->statusCode);
        self::assertSame('spart-php-sdk/1.0', $response->headers['x-echo-user-agent'] ?? null);
    }

    public function test_send_uses_custom_user_agent_when_provided(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'ok'],
        ]);

        $client = new CurlClient(userAgent: 'MyMerchant/2.3.4 (PHP 8.1)');
        $response = $client->send(new HttpRequest(
            method: 'GET',
            url: $this->stub->url('/x'),
            headers: [],
            body: null,
            timeoutSeconds: 5,
        ));

        self::assertSame(200, $response->statusCode);
        self::assertSame('MyMerchant/2.3.4 (PHP 8.1)', $response->headers['x-echo-user-agent'] ?? null);
    }

    public function test_send_user_agent_persists_across_multiple_requests(): void
    {
        // CurlClient creates a fresh curl handle per request; verify that
        // the configured UA isn't accidentally state-on-first-use that
        // leaks or resets between calls.
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'first'],
            ['status' => 200, 'body' => 'second'],
        ]);

        $client = new CurlClient(userAgent: 'PersistTest/1.0');
        $r1 = $client->send(new HttpRequest('GET', $this->stub->url('/a'), [], null, 5));
        $r2 = $client->send(new HttpRequest('GET', $this->stub->url('/b'), [], null, 5));

        self::assertSame('PersistTest/1.0', $r1->headers['x-echo-user-agent'] ?? null);
        self::assertSame('PersistTest/1.0', $r2->headers['x-echo-user-agent'] ?? null);
    }

    // --- Constructor validation: defense-in-depth on the public ctor.
    //     SpartClientConfig already validates UAs, but CurlClient is a
    //     public class that callers can construct directly (e.g. in tests
    //     or custom factories), so the same rules MUST apply here. The
    //     deeper rule set is exercised in UserAgentValidatorTest; these
    //     tests just prove the validator is wired in.

    public function test_constructor_rejects_blank_user_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlClient(userAgent: '   ');
    }

    public function test_constructor_rejects_user_agent_with_carriage_return(): void
    {
        // The exploit this prevents: a CR/LF in CURLOPT_USERAGENT
        // smuggles `X-Injected: yes` as a separate outbound header.
        $this->expectException(\InvalidArgumentException::class);
        new CurlClient(userAgent: "MyApp/1.0\r\nX-Injected: yes");
    }

    public function test_constructor_rejects_user_agent_with_line_feed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlClient(userAgent: "MyApp/1.0\nX-Injected: yes");
    }

    public function test_constructor_rejects_user_agent_with_null_byte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlClient(userAgent: "MyApp/1.0\0junk");
    }

    public function test_constructor_rejects_oversized_user_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlClient(userAgent: str_repeat('a', 1025));
    }

    public function test_constructor_accepts_null_and_uses_sdk_default(): void
    {
        // Construction succeeds; UA wiring is asserted by the
        // test_send_uses_default_user_agent_when_none_provided test above.
        $client = new CurlClient(userAgent: null);
        self::assertInstanceOf(CurlClient::class, $client);
    }
}
