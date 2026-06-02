<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Tests\Integration\RawHttp;

/**
 * Fast smoke tests for the new `RawHttp::get()`, `RawHttp::request()`
 * and `RawHttp::parallel()` helpers. Spins up a tiny `php -S` fixture
 * (see Fixtures/router.php) so the test runs in the default `sdk`
 * suite without needing the full Spart API stack.
 */
final class RawHttpTest extends TestCase
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->startFixture();
        $this->waitUntilReady();
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->process)) {
            proc_terminate($this->process);

            // Bound the wait on the child so a stuck `php -S` cannot hang
            // the entire PHPUnit runner. Escalate to SIGKILL after 2s.
            $exited = false;
            $deadline = microtime(true) + 2.0;
            while (microtime(true) < $deadline) {
                $status = proc_get_status($this->process);
                if (!$status['running']) {
                    $exited = true;
                    break;
                }
                usleep(50_000);
            }
            if (!$exited) {
                proc_terminate($this->process, 9);
            }

            proc_close($this->process);
            $this->process = null;
        }
    }

    public function test_get_returns_status_headers_and_body(): void
    {
        $resp = RawHttp::get($this->baseUrl . '/echo204', ['Accept: application/json']);

        self::assertSame(204, $resp[RawHttp::STATUS]);
        self::assertSame('', $resp[RawHttp::BODY]);
        self::assertContains('X-Echo-Path: /echo204', $resp[RawHttp::HEADERS]);
    }

    public function test_parallel_returns_one_response_per_request(): void
    {
        $reqs = [
            ['method' => 'GET', 'url' => $this->baseUrl . '/echo200/a'],
            ['method' => 'GET', 'url' => $this->baseUrl . '/echo200/b'],
            ['method' => 'GET', 'url' => $this->baseUrl . '/echo200/c'],
        ];

        $responses = RawHttp::parallel($reqs);

        self::assertCount(3, $responses);
        $expected = ['/echo200/a', '/echo200/b', '/echo200/c'];
        foreach ($responses as $i => $r) {
            self::assertSame(200, $r[RawHttp::STATUS]);
            self::assertSame('ok', $r[RawHttp::BODY]);
            self::assertContains('X-Echo-Path: ' . $expected[$i], $r[RawHttp::HEADERS]);
        }
    }

    public function test_parallel_preserves_request_order_when_completion_order_is_reversed(): void
    {
        // Issue requests with DESCENDING sleep times so completion order is the
        // reverse of request order. The only way responses[0] still maps to
        // /slow/300 is if the implementation preserves request-order — i.e.
        // proves the ksort($responses) branch in RawHttp::parallel actually
        // matters, vs. the previous test which would silently pass against a
        // hypothetical completion-order implementation.
        $reqs = [
            ['method' => 'GET', 'url' => $this->baseUrl . '/slow/300'],
            ['method' => 'GET', 'url' => $this->baseUrl . '/slow/150'],
            ['method' => 'GET', 'url' => $this->baseUrl . '/slow/0'],
        ];

        $responses = RawHttp::parallel($reqs);

        self::assertCount(3, $responses);
        $expectedDelays = ['300', '150', '0'];
        foreach ($responses as $i => $r) {
            self::assertSame(200, $r[RawHttp::STATUS]);
            self::assertSame(
                $expectedDelays[$i],
                $r[RawHttp::BODY],
                "response[$i] body should echo {$expectedDelays[$i]} (proves request-order preservation)",
            );
            self::assertContains('X-Echo-Path: /slow/' . $expectedDelays[$i], $r[RawHttp::HEADERS]);
        }
    }

    private function startFixture(): void
    {
        $port = self::findFreePort();
        $router = __DIR__ . '/Fixtures/router.php';

        $cmd = sprintf(
            'exec php -S 127.0.0.1:%d %s',
            $port,
            escapeshellarg($router),
        );

        /** @var array<int, array{0: string, 1: string}> $descriptors */
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            self::markTestSkipped('proc_open unavailable; cannot start php -S fixture');
        }

        $this->process = $proc;
        /** @var array<int, resource> $pipes */
        $this->pipes = $pipes;
        $this->baseUrl = "http://127.0.0.1:$port";
    }

    private static function findFreePort(): int
    {
        $errno = 0;
        $errstr = '';
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            self::fail("Could not bind ephemeral port: $errstr ($errno)");
        }

        $name = stream_socket_get_name($sock, false);
        fclose($sock);

        if ($name === false) {
            self::fail('Could not read bound port name');
        }

        $colon = strrpos($name, ':');
        if ($colon === false) {
            self::fail("Unexpected socket name format: $name");
        }

        return (int) substr($name, $colon + 1);
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            try {
                $resp = RawHttp::get($this->baseUrl . '/health', [], 1);
                if ($resp[RawHttp::STATUS] === 200) {
                    return;
                }
            } catch (\Throwable) {
                // Server not yet ready — keep polling until the deadline.
            }
            usleep(50_000);
        }

        self::fail('php -S fixture did not become ready within 5s');
    }
}
