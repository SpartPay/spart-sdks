<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Integration;

/**
 * Tiny native-cURL helper for E2E tests that need to send raw HTTP
 * (bypassing the SDK's request DTOs / client-side validation) so the
 * server's response codes can be asserted directly. Avoids adding a
 * Guzzle dependency just for two test files.
 *
 * In addition to {@see self::postJson()} (which is kept unchanged for
 * backward compatibility), the helper exposes {@see self::get()},
 * {@see self::request()} and {@see self::parallel()}. Those richer
 * helpers return the response status, the parsed response header lines
 * and the response body so tests can introspect headers such as
 * `Retry-After`, `X-RateLimit-*` or `ETag`.
 *
 * @internal
 */
final class RawHttp
{
    public const STATUS = 'status';
    public const HEADERS = 'headers';
    public const BODY = 'body';

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public static function postJson(
        string $url,
        array $headers,
        string $body,
        int $timeoutSeconds = 15,
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed.');
        }

        $headerLines = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new \RuntimeException("curl error contacting $url: $err");
        }

        return [self::STATUS => $status, self::BODY => (string) $responseBody];
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, headers: list<string>, body: string}
     */
    public static function get(string $url, array $headers = [], int $timeoutSeconds = 10): array
    {
        return self::request('GET', $url, $headers, null, $timeoutSeconds);
    }

    /**
     * @param list<string>             $headers
     * @param array<string, mixed>|null $body JSON-encoded when not null.
     * @return array{status: int, headers: list<string>, body: string}
     */
    public static function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $body = null,
        int $timeoutSeconds = 10,
    ): array {
        // Encode the body BEFORE allocating the curl handle so a JsonException
        // from JSON_THROW_ON_ERROR cannot leak the handle.
        $encodedBody = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : null;

        $ch = curl_init();
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($encodedBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP transport error: $err");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawString = (string) $raw;
        $headerBlob = substr($rawString, 0, $headerSize);
        $bodyText = substr($rawString, $headerSize);

        return [
            self::STATUS  => $status,
            self::HEADERS => self::splitHeaderBlob($headerBlob),
            self::BODY    => $bodyText,
        ];
    }

    /**
     * @param list<array{method: string, url: string, headers?: list<string>, body?: array<string, mixed>}> $reqs
     * @return list<array{status: int, headers: list<string>, body: string}>
     */
    public static function parallel(array $reqs, int $timeoutSeconds = 10): array
    {
        // Pre-encode all bodies BEFORE allocating any curl handles so a
        // JsonException from JSON_THROW_ON_ERROR cannot leak handles or the
        // multi-handle. Index alignment with $reqs is preserved.
        /** @var array<int, string|null> $encodedBodies */
        $encodedBodies = [];
        foreach ($reqs as $i => $req) {
            $encodedBodies[$i] = array_key_exists('body', $req)
                ? json_encode($req['body'], JSON_THROW_ON_ERROR)
                : null;
        }

        // curl_multi_init() returns CurlMultiHandle (no longer |false) since
        // PHP 8.0, so no return-value guard is needed; PHPStan flags one as
        // dead code.
        $mh = curl_multi_init();

        /** @var array<int, \CurlHandle> $handles */
        $handles = [];

        foreach ($reqs as $i => $req) {
            $ch = curl_init();
            if ($ch === false) {
                foreach ($handles as $existing) {
                    curl_multi_remove_handle($mh, $existing);
                    curl_close($existing);
                }
                curl_multi_close($mh);
                throw new \RuntimeException('curl_init failed.');
            }

            curl_setopt_array($ch, [
                CURLOPT_URL            => $req['url'],
                CURLOPT_CUSTOMREQUEST  => strtoupper($req['method']),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
                CURLOPT_TIMEOUT        => $timeoutSeconds,
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            if ($encodedBodies[$i] !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBodies[$i]);
            }

            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running);

        /** @var array<int, array{status: int, headers: list<string>, body: string}> $responses */
        $responses = [];
        foreach ($handles as $i => $ch) {
            $raw = (string) curl_multi_getcontent($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerBlob = substr($raw, 0, $headerSize);
            $bodyText = substr($raw, $headerSize);
            $responses[$i] = [
                self::STATUS  => $status,
                self::HEADERS => self::splitHeaderBlob($headerBlob),
                self::BODY    => $bodyText,
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        ksort($responses);

        return array_values($responses);
    }

    /**
     * @return list<string>
     */
    private static function splitHeaderBlob(string $blob): array
    {
        $parts = preg_split("/\r?\n/", $blob);
        if ($parts === false) {
            return [];
        }

        return array_values(array_filter(
            array_map('rtrim', $parts),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
