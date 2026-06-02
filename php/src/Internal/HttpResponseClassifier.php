<?php

declare(strict_types=1);

namespace Spart\Sdk\Internal;

use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Exceptions\SpartRateLimitException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Exceptions\SpartValidationException;
use Spart\Sdk\Http\HttpResponse;

/**
 * Maps an HTTP failure response (any non-2xx status) onto a typed
 * Spart exception. Lives independently of {@see ResponseMapper} so
 * the same status-to-exception classification can be applied from:
 *
 *  - {@see \Spart\Sdk\Http\Curl\CurlClient}, BEFORE the response
 *    crosses the {@see \Spart\Sdk\Http\RetryingHttpClient} decorator
 *    boundary — required so 5xx and 429 responses are seen by the
 *    retry pipeline as exceptions and trigger the configured policy.
 *  - {@see ResponseMapper}, as a defensive layer when the success
 *    path is bypassed (e.g. directly mapping a stub HttpResponse in
 *    unit tests).
 *
 * The classifier ALWAYS throws — calling code asserts that 200/201
 * has been ruled out before delegating here. 2xx is a programming
 * error and yields a {@see \LogicException} so the bug surfaces at
 * the boundary rather than a few stack frames later.
 *
 * Failure envelope shape (issue #197):
 *   {"isSuccessful": false, "value": null,
 *    "error": {"code": "intent.session_conflict",
 *              "message": "…",
 *              "additionalData": [...] | object | null}}
 *
 * @internal
 */
final class HttpResponseClassifier
{
    /**
     * Reads optional `error` object from the response body
     * (already JSON-decoded). Failure-shape envelopes carry an
     * `error` object with `code`, `message`, and optional
     * `additionalData`; bodyless 401/429/5xx responses do not,
     * in which case default messages are used.
     *
     * @return never
     */
    public static function throwForFailureStatus(HttpResponse $response): never
    {
        $code = $response->statusCode;
        if ($code >= 200 && $code <= 299) {
            throw new \LogicException(
                "HttpResponseClassifier::throwForFailureStatus called with success status {$code}.",
            );
        }

        $body = self::decodeJson($response->body);
        $message = self::extractErrorMessage($body, $code);
        $errorCode = self::extractErrorCode($body);
        $details = self::extractErrorDetails($body);

        if ($code === 401) {
            throw new SpartAuthException(
                $message,
                errorCode: $errorCode,
                errorDetails: $details,
                rawBody: $body,
            );
        }
        if ($code === 400) {
            throw new SpartValidationException(
                $message,
                statusCode: 400,
                errorCode: $errorCode,
                errorDetails: $details,
                rawBody: $body,
            );
        }
        if ($code === 429) {
            $retryAfter = $response->header('retry-after');
            $retrySeconds = $retryAfter !== null && ctype_digit($retryAfter) ? (int) $retryAfter : null;
            throw new SpartRateLimitException(
                $message,
                retryAfterSeconds: $retrySeconds,
                errorCode: $errorCode,
                errorDetails: $details,
                rawBody: $body,
            );
        }
        if ($code >= 500 && $code <= 599) {
            throw new SpartServerException(
                $message,
                statusCode: $code,
                errorCode: $errorCode,
                errorDetails: $details,
                rawBody: $body,
            );
        }
        throw new SpartApiException(
            $message,
            statusCode: $code,
            errorCode: $errorCode,
            errorDetails: $details,
            rawBody: $body,
        );
    }

    /** @return array<string,mixed>|null */
    private static function decodeJson(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed>|null $body */
    private static function extractErrorMessage(?array $body, int $code): string
    {
        if ($body !== null && isset($body['error']) && is_array($body['error'])) {
            $msg = $body['error']['message'] ?? null;
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }
        return match (true) {
            $code === 401 => 'Unauthorized.',
            $code === 429 => 'Rate limited.',
            $code >= 500 => "Spart API server error (HTTP {$code}).",
            default => "Spart API request failed (HTTP {$code}).",
        };
    }

    /** @param array<string,mixed>|null $body */
    private static function extractErrorCode(?array $body): ?string
    {
        if ($body !== null && isset($body['error']) && is_array($body['error'])) {
            $code = $body['error']['code'] ?? null;
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }
        return null;
    }

    /**
     * Returns a flat `list<string>` view of the error's `additionalData`,
     * suitable for surfacing as `SpartApiException::$errorDetails`.
     * Strings are kept verbatim; `{"code": ..., "message": ...}` objects
     * are flattened to their `message` (the human-readable text). Any
     * other shape is skipped to keep the public API simple. Callers that
     * need the full structured payload can read `rawBody['error']['additionalData']`.
     *
     * @param array<string,mixed>|null $body
     * @return list<string>
     */
    private static function extractErrorDetails(?array $body): array
    {
        if ($body === null || !isset($body['error']) || !is_array($body['error'])) {
            return [];
        }
        $additional = $body['error']['additionalData'] ?? null;
        if (!is_array($additional)) {
            return [];
        }
        $out = [];
        foreach ($additional as $entry) {
            if (is_string($entry) && $entry !== '') {
                $out[] = $entry;
                continue;
            }
            if (
                is_array($entry)
                && isset($entry['message'])
                && is_string($entry['message'])
                && $entry['message'] !== ''
            ) {
                $out[] = $entry['message'];
            }
        }
        return $out;
    }
}
