<?php

/**
 * Minimal router script for the `php -S` fixture used by RawHttpTest.
 *
 * Four deterministic routes — no env-var indirection needed:
 *
 *   GET /health           -> 200 "ok"
 *   GET /echo204          -> 204 (empty body)
 *   GET /echo200/<token>  -> 200 "ok"
 *   GET /slow/<ms>        -> 200 "<ms>" (sleeps ms milliseconds first)
 *
 * Every response carries an `X-Echo-Path` header echoing
 * `$_SERVER['REQUEST_URI']` so tests can assert per-request response
 * ordering when issuing concurrent requests via `RawHttp::parallel()`.
 *
 * The `/slow/<ms>` route exists specifically so the parallel test can
 * issue requests with descending sleep times: completion order then
 * differs from request order, which is the only way to actually prove
 * that `RawHttp::parallel` preserves request-order.
 */

declare(strict_types=1);

$path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
header('X-Echo-Path: ' . $path);

if ($path === '/health') {
    http_response_code(200);
    echo 'ok';
    return true;
}

if ($path === '/echo200' || str_starts_with($path, '/echo200/')) {
    http_response_code(200);
    echo 'ok';
    return true;
}

if ($path === '/echo204') {
    http_response_code(204);
    return true;
}

if (preg_match('#^/slow/(\d+)$#', $path, $m) === 1) {
    usleep((int) $m[1] * 1000);
    http_response_code(200);
    echo $m[1];
    return true;
}

http_response_code(404);
echo 'not found';
return true;
