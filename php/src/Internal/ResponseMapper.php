<?php

declare(strict_types=1);

namespace Spart\Sdk\Internal;

use Spart\Sdk\Dtos\Eligibility;
use Spart\Sdk\Dtos\IntentDetails;
use Spart\Sdk\Dtos\IntentList;
use Spart\Sdk\Dtos\IntentResult;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Http\HttpResponse;

/**
 * Maps an HTTP response from the Spart API into either an {@see IntentResult}
 * or a typed Spart exception. Understands the server's `Result<T>` envelope:
 *
 *   Success: `{"isSuccessful": true, "value": {...}, "error": null}`
 *   Failure: `{"isSuccessful": false, "value": null,
 *             "error": {"code": "...", "message": "...", "additionalData": [...]}}`
 *             (or bodyless)
 *
 * Failure mapping is status-code-first: 401 / 429 are sometimes bodyless
 * (auth filter / rate limiter return plain `Unauthorized()` / `TooManyRequests`)
 * so the mapper does not require any envelope shape on the failure path.
 *
 * @internal
 */
final class ResponseMapper
{
    public static function toIntentResult(HttpResponse $response): IntentResult
    {
        $code = $response->statusCode;
        $body = self::decodeJson($response->body);

        if ($code === 200 || $code === 201) {
            return self::parseSuccess($body, $code);
        }

        self::throwForFailureStatus($response, $body);
    }

    /**
     * Maps a `GET /api/intents/{shortId}` response into {@see IntentDetails}.
     * Shares the failure-mapping branches with {@see toIntentResult} so the
     * SDK presents a single uniform exception surface across endpoints.
     *
     * The success envelope is the same `Result<T>` shape used by create:
     * `{"isSuccessful": true, "value": {...}, "error": null}`.
     * The 200/201 split that distinguishes new-vs-replay on the create path
     * does NOT apply here — read endpoints are always 200 on success.
     */
    public static function toIntentDetails(HttpResponse $response): IntentDetails
    {
        $code = $response->statusCode;
        $body = self::decodeJson($response->body);

        if ($code === 200) {
            return self::parseIntentDetailsSuccess($body, $code);
        }

        self::throwForFailureStatus($response, $body);
    }

    /**
     * Maps a `GET /api/intents` paged-collection response into
     * {@see IntentList}. Same `Result<T>` envelope as create/get;
     * failure mapping shared via {@see throwForFailureStatus}.
     */
    public static function toIntentList(HttpResponse $response): IntentList
    {
        $code = $response->statusCode;
        $body = self::decodeJson($response->body);

        if ($code === 200) {
            return self::parseIntentListSuccess($body, $code);
        }

        self::throwForFailureStatus($response, $body);
    }

    /**
     * Maps a `GET /api/merchants/eligibility` response into {@see Eligibility}.
     * Shares the failure-mapping branches with {@see toIntentResult} so the
     * SDK presents a single uniform exception surface across endpoints.
     *
     * The success envelope is the same `Result<T>` shape used by intents:
     * `{"isSuccessful": true, "value": {...}, "error": null}`. Read endpoint —
     * always 200 on success.
     */
    public static function toEligibility(HttpResponse $response): Eligibility
    {
        $code = $response->statusCode;
        $body = self::decodeJson($response->body);

        if ($code === 200) {
            return self::parseEligibilitySuccess($body, $code);
        }

        self::throwForFailureStatus($response, $body);
    }

    /**
     * @param array<string,mixed>|null $body
     * @return never
     */
    private static function throwForFailureStatus(HttpResponse $response, ?array $body): never
    {
        // Delegate to the shared classifier so the same status-to-exception
        // mapping applies whether the failure is observed by the
        // RetryingHttpClient (via CurlClient) or by a unit test that
        // hands a stubbed HttpResponse directly to ResponseMapper.
        // The decoded $body is intentionally re-decoded inside the
        // classifier for symmetry with the CurlClient call site.
        unset($body);
        HttpResponseClassifier::throwForFailureStatus($response);
    }

    /** @param array<string,mixed>|null $body */
    private static function parseIntentListSuccess(?array $body, int $code): IntentList
    {
        if ($body === null) {
            throw new SpartTransportException(
                "Spart API returned non-JSON body for HTTP {$code}.",
            );
        }
        if (!array_key_exists('value', $body) || !is_array($body['value'])) {
            throw new SpartApiException(
                'Spart API success envelope missing "value" object.',
                statusCode: $code,
                rawBody: $body,
            );
        }
        try {
            return IntentList::fromArray($body['value']);
        } catch (\InvalidArgumentException $e) {
            throw new SpartApiException(
                'Spart API returned a malformed IntentList body: ' . $e->getMessage(),
                statusCode: $code,
                rawBody: $body,
                previous: $e,
            );
        }
    }

    /** @param array<string,mixed>|null $body */
    private static function parseIntentDetailsSuccess(?array $body, int $code): IntentDetails
    {
        if ($body === null) {
            throw new SpartTransportException(
                "Spart API returned non-JSON body for HTTP {$code}.",
            );
        }
        if (!array_key_exists('value', $body) || !is_array($body['value'])) {
            throw new SpartApiException(
                'Spart API success envelope missing "value" object.',
                statusCode: $code,
                rawBody: $body,
            );
        }
        try {
            return IntentDetails::fromArray($body['value']);
        } catch (\InvalidArgumentException $e) {
            throw new SpartApiException(
                'Spart API returned a malformed IntentDetails body: ' . $e->getMessage(),
                statusCode: $code,
                rawBody: $body,
                previous: $e,
            );
        }
    }

    /** @param array<string,mixed>|null $body */
    private static function parseEligibilitySuccess(?array $body, int $code): Eligibility
    {
        if ($body === null) {
            throw new SpartTransportException(
                "Spart API returned non-JSON body for HTTP {$code}.",
            );
        }
        if (!array_key_exists('value', $body) || !is_array($body['value'])) {
            throw new SpartApiException(
                'Spart API success envelope missing "value" object.',
                statusCode: $code,
                rawBody: $body,
            );
        }
        try {
            return Eligibility::fromArray($body['value']);
        } catch (\InvalidArgumentException $e) {
            throw new SpartApiException(
                'Spart API returned a malformed Eligibility body: ' . $e->getMessage(),
                statusCode: $code,
                rawBody: $body,
                previous: $e,
            );
        }
    }

    /** @param array<string,mixed>|null $body */
    private static function parseSuccess(?array $body, int $code): IntentResult
    {
        if ($body === null) {
            // Non-JSON in a 2xx response means the wire is broken (proxy
            // rewrote the body, gateway swallowed the response, etc.) —
            // not an API-level error. Mapping to SpartTransportException
            // lets RetryingHttpClient treat this as a transient failure
            // and retry the request.
            throw new SpartTransportException(
                "Spart API returned non-JSON body for HTTP {$code}.",
            );
        }
        if (!array_key_exists('value', $body) || !is_array($body['value'])) {
            throw new SpartApiException(
                'Spart API success envelope missing "value" object.',
                statusCode: $code,
                rawBody: $body,
            );
        }
        try {
            return IntentResult::fromArray($body['value'], wasReplay: $code === 200);
        } catch (\InvalidArgumentException $e) {
            throw new SpartApiException(
                'Spart API returned a malformed success body: ' . $e->getMessage(),
                statusCode: $code,
                rawBody: $body,
                previous: $e,
            );
        }
    }

    /** @return array<string,mixed>|null */
    private static function decodeJson(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }
}
