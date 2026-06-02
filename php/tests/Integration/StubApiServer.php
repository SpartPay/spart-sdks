<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Integration;

/**
 * Boots a tiny PHP built-in HTTP server (`php -S`) in a child process and
 * serves scripted HTTP responses backed by a JSON cursor file. Used by
 * retry / backoff E2E tests where the real Spart API would be impractical
 * to drive into specific failure modes (503s, slow responses, custom
 * Retry-After headers, etc.).
 *
 * Each request consumes the next entry from the script. After the script
 * is exhausted, all subsequent requests return HTTP 500 with body
 * "no more scripted responses" so accidental over-consumption surfaces
 * as a clear test failure rather than a silent reuse of the last entry.
 *
 * **Concurrency:** sequential single-client only. The cursor file is
 * read-modify-written by the fixture script without locking, so
 * concurrent requests would race. Adequate for retry tests, where
 * one client issues sequential requests.
 *
 * Mirrors the {@see RawHttp} helper's "exec-prefixed proc_open + bound
 * tearDown with SIGKILL escalation" pattern from
 * {@see \Spart\Sdk\Tests\Unit\Integration\RawHttpTest} so `proc_terminate`
 * actually kills the `php -S` child and a stuck server cannot hang the
 * PHPUnit runner.
 *
 * @internal
 */
final class StubApiServer
{
    /** @var resource */
    private $process;

    /** @var array<int, resource> */
    private array $pipes;

    private int $port;

    private string $scriptPath;

    /**
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private function __construct($process, array $pipes, int $port, string $scriptPath)
    {
        $this->process = $process;
        $this->pipes = $pipes;
        $this->port = $port;
        $this->scriptPath = $scriptPath;
    }

    /**
     * @param list<array{status:int, body?:string, delayMs?:int, headers?:array<string,string>}> $script
     */
    public static function start(array $script): self
    {
        $port = self::findFreePort();
        $scriptFile = sys_get_temp_dir() . '/spart-stub-' . bin2hex(random_bytes(6)) . '.json';

        $encoded = json_encode(
            ['script' => $script, 'cursor' => 0],
            JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($scriptFile, $encoded) === false) {
            throw new \RuntimeException("Failed to write stub script file: $scriptFile");
        }

        $entry = __DIR__ . '/Fixtures/stub-server.php';
        // `exec` so proc_terminate signals the `php -S` child directly,
        // not a wrapping /bin/sh. `env` sets the env var since we're now
        // exec-ing rather than running through a shell.
        $cmd = sprintf(
            'exec env SPART_STUB_FILE=%s php -S 127.0.0.1:%d %s',
            escapeshellarg($scriptFile),
            $port,
            escapeshellarg($entry),
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
            @unlink($scriptFile);
            throw new \RuntimeException('Failed to start stub server (proc_open returned false).');
        }

        /** @var array<int, resource> $pipes */

        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $errno = 0;
            $errstr = '';
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($sock !== false) {
                fclose($sock);
                return new self($proc, $pipes, $port, $scriptFile);
            }
            usleep(50_000);
        }

        // Best-effort cleanup if the server never became ready. Bounded
        // identically to stop() — see shutdownProcess() — so a stuck
        // `php -S` cannot hang the PHPUnit runner via proc_close.
        self::shutdownProcess($proc, $pipes);
        @unlink($scriptFile);

        throw new \RuntimeException("Stub server on port $port never became ready within 5s.");
    }

    public function url(string $path = '/'): string
    {
        return "http://127.0.0.1:{$this->port}{$path}";
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * Stops the child process and cleans up the cursor file. Bounded so a
     * stuck server cannot hang the PHPUnit runner: SIGTERM first, then
     * SIGKILL after 2s.
     */
    public function stop(): void
    {
        self::shutdownProcess($this->process, $this->pipes);
        $this->pipes = [];

        if (is_file($this->scriptPath)) {
            @unlink($this->scriptPath);
        }
    }

    /**
     * Closes pipes, sends SIGTERM, polls up to 2s for the process to
     * exit, escalates to SIGKILL if still running, then proc_close.
     * Used by both stop() (normal teardown) and start() (readiness
     * timeout cleanup) so neither path can hang on proc_close.
     *
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private static function shutdownProcess($process, array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }

        if (!is_resource($process)) {
            return;
        }

        @proc_terminate($process);

        $exited = false;
        $deadline = microtime(true) + 2.0;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exited = true;
                break;
            }
            usleep(50_000);
        }
        if (!$exited) {
            @proc_terminate($process, 9);
        }

        @proc_close($process);
    }

    private static function findFreePort(): int
    {
        $errno = 0;
        $errstr = '';
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            throw new \RuntimeException("Could not bind ephemeral port: $errstr ($errno)");
        }

        $name = stream_socket_get_name($sock, false);
        fclose($sock);

        if ($name === false) {
            throw new \RuntimeException('Could not read bound port name from stream_socket_server.');
        }

        $colon = strrpos($name, ':');
        if ($colon === false) {
            throw new \RuntimeException("Unexpected socket name format: $name");
        }

        return (int) substr($name, $colon + 1);
    }
}
