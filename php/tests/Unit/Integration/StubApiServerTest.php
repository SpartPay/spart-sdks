<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Tests\Integration\RawHttp;
use Spart\Sdk\Tests\Integration\StubApiServer;

/**
 * Fast unit tests for the {@see StubApiServer} helper. The stub server is
 * a tiny `php -S` child process that serves scripted HTTP responses from
 * a JSON-backed cursor file; it powers retry / backoff E2E tests where
 * the real Spart API would be impractical to drive into specific failure
 * modes (503s, slow responses, custom headers).
 *
 * These tests live in `tests/Unit/E2E` so they run in the default `sdk`
 * suite alongside {@see RawHttpTest}, not in the slow `e2e` suite.
 *
 * Each test covers one branch of the helper's contract; downstream retry
 * tests rely on every branch being correct, so we exercise them all here
 * rather than later through the indirection of the SDK.
 */
final class StubApiServerTest extends TestCase
{
    private ?StubApiServer $stub = null;

    protected function tearDown(): void
    {
        if ($this->stub !== null) {
            $this->stub->stop();
            $this->stub = null;
        }
    }

    public function test_serves_scripted_responses_in_order(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 503, 'body' => '{"error":"unavailable"}'],
            ['status' => 200, 'body' => '{"ok":true}'],
        ]);

        $first = RawHttp::get($this->stub->url('/x'));
        $second = RawHttp::get($this->stub->url('/x'));

        self::assertSame(503, $first[RawHttp::STATUS]);
        self::assertSame('{"error":"unavailable"}', $first[RawHttp::BODY]);
        self::assertSame(200, $second[RawHttp::STATUS]);
        self::assertSame('{"ok":true}', $second[RawHttp::BODY]);
    }

    public function test_serves_responses_in_strict_script_order(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'first'],
            ['status' => 503, 'body' => 'second'],
            ['status' => 200, 'body' => 'third'],
        ]);

        $a = RawHttp::get($this->stub->url('/a'));
        $b = RawHttp::get($this->stub->url('/b'));
        $c = RawHttp::get($this->stub->url('/c'));

        self::assertSame(200, $a[RawHttp::STATUS]);
        self::assertSame('first', $a[RawHttp::BODY]);
        self::assertSame(503, $b[RawHttp::STATUS]);
        self::assertSame('second', $b[RawHttp::BODY]);
        self::assertSame(200, $c[RawHttp::STATUS]);
        self::assertSame('third', $c[RawHttp::BODY]);
    }

    public function test_returns_500_no_more_scripted_responses_after_exhaustion(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'only'],
        ]);

        $first = RawHttp::get($this->stub->url('/x'));
        $second = RawHttp::get($this->stub->url('/x'));

        self::assertSame(200, $first[RawHttp::STATUS]);
        self::assertSame('only', $first[RawHttp::BODY]);
        self::assertSame(500, $second[RawHttp::STATUS]);
        self::assertStringContainsString('no more scripted responses', $second[RawHttp::BODY]);
    }

    public function test_honors_delayMs(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'slow', 'delayMs' => 200],
        ]);

        $start = microtime(true);
        $resp = RawHttp::get($this->stub->url('/x'));
        $elapsedMs = (microtime(true) - $start) * 1000;

        self::assertSame(200, $resp[RawHttp::STATUS]);
        self::assertSame('slow', $resp[RawHttp::BODY]);
        // 180ms tolerance below the scripted 200ms delay: PHP's usleep
        // is "at least" the requested duration in spec but signal
        // interrupts and scheduler imprecision can occasionally allow
        // slightly-early wakes on busy CI runners.
        self::assertGreaterThanOrEqual(
            180,
            $elapsedMs,
            "request returned in {$elapsedMs}ms; expected >= 180ms (delayMs=200, 20ms tolerance)",
        );
    }

    public function test_passes_through_custom_headers(): void
    {
        $this->stub = StubApiServer::start([
            ['status' => 200, 'body' => 'ok', 'headers' => ['X-Custom' => 'foo', 'X-Other' => 'bar']],
        ]);

        $resp = RawHttp::get($this->stub->url('/x'));

        self::assertSame(200, $resp[RawHttp::STATUS]);
        self::assertContains('X-Custom: foo', $resp[RawHttp::HEADERS]);
        self::assertContains('X-Other: bar', $resp[RawHttp::HEADERS]);
    }
}
