<?php

/**
 * Router script for the {@see \Spart\Sdk\Tests\Integration\StubApiServer} `php -S`
 * fixture. On every request, atomically (single-client only — see helper
 * docblock) advances the cursor in the JSON state file pointed to by
 * SPART_STUB_FILE and emits the next scripted response.
 *
 * After the script is exhausted, returns HTTP 500 with body
 * "no more scripted responses" so accidental over-consumption surfaces as
 * a clear test failure rather than silently reusing the last entry.
 *
 * Each entry supports:
 *   - status (int, required)
 *   - body (string, optional, default "")
 *   - headers (array<string,string>, optional)
 *   - delayMs (int, optional) — sleeps server-side before responding
 */

declare(strict_types=1);

$file = getenv('SPART_STUB_FILE');
if ($file === false || !is_file($file)) {
    http_response_code(500);
    echo 'no script file';
    return true;
}

$raw = file_get_contents($file);
if ($raw === false) {
    http_response_code(500);
    echo 'failed to read script file';
    return true;
}

try {
    /** @var array{script: list<array{status:int, body?:string, delayMs?:int, headers?:array<string,string>}>, cursor: int} $state */
    $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    http_response_code(500);
    echo 'malformed script file: ' . $e->getMessage();
    return true;
}

$cursor = $state['cursor'] ?? 0;
$script = $state['script'] ?? [];
$step = $script[$cursor] ?? null;

$state['cursor'] = $cursor + 1;
file_put_contents($file, json_encode($state, JSON_THROW_ON_ERROR));

if ($step === null) {
    http_response_code(500);
    echo 'no more scripted responses';
    return true;
}

if (isset($step['delayMs']) && is_int($step['delayMs']) && $step['delayMs'] > 0) {
    usleep($step['delayMs'] * 1000);
}

http_response_code((int) $step['status']);

// Always echo the inbound User-Agent back as a response header so tests
// that need to verify "the SDK sent the configured UA" can read it
// directly without any additional script wiring. Header is omitted
// when the request didn't carry a UA.
$inboundUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($inboundUa !== '') {
    header('X-Echo-User-Agent: ' . $inboundUa);
}

if (isset($step['headers']) && is_array($step['headers'])) {
    foreach ($step['headers'] as $name => $value) {
        header("$name: $value");
    }
}

echo (string) ($step['body'] ?? '');
return true;
