<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Integration\Fixtures;

/**
 * Builds canonical Spart API JSON response envelopes for fixture-driven
 * tests (notably {@see \Spart\Sdk\Tests\Integration\StubApiServer}). Centralised
 * so the wire-shape contract lives in exactly one place: drift between
 * the SDK's expected envelope and the test fixtures shows up as a
 * single-file change.
 *
 * @internal — test-only fixture; not part of the SDK contract.
 */
final class IntentResponseFixture
{
    /**
     * Successful `POST /api/intents` body — the create-intent surface.
     *
     * @return array<string,mixed>
     */
    public static function createOk(string $intentShortId = 'abc123'): array
    {
        return [
            'isSuccessful' => true,
            'value' => [
                'intentShortId' => $intentShortId,
                'checkoutUrl' => "https://checkout.example.test/c/{$intentShortId}",
            ],
            'error' => null,
        ];
    }

    /**
     * Successful `GET /api/intents/{shortId}` body — the read-intent surface.
     *
     * @return array<string,mixed>
     */
    public static function readOk(string $intentShortId = 'abc123'): array
    {
        return [
            'isSuccessful' => true,
            'value' => [
                'shortId' => $intentShortId,
                'total' => ['value' => 100.0, 'currency' => 'CAD'],
                'lineItems' => [
                    ['name' => 'Widget', 'quantity' => 1, 'description' => null, 'imageUri' => null],
                ],
                'createdAt' => '2026-05-11T12:34:56+00:00',
                'orderId' => null,
                'sessionId' => 'sess_xyz',
                'isCompleted' => false,
                'links' => [
                    ['name' => 'Checkout', 'url' => "https://checkout.example.test/c/{$intentShortId}"],
                ],
            ],
            'error' => null,
        ];
    }

    /**
     * Failed envelope used when the server signals an application-level
     * error (validation, missing field, etc.) — issue #197 envelope shape:
     * `error` is an object with `code`, `message`, and optional `additionalData`.
     *
     * @param list<string>|list<array{code:string,message:string}>|null $additionalData
     * @return array<string,mixed>
     */
    public static function failure(
        string $code = 'server.error',
        string $message = 'something went wrong',
        ?array $additionalData = null,
    ): array {
        $error = ['code' => $code, 'message' => $message];
        if ($additionalData !== null) {
            $error['additionalData'] = $additionalData;
        }
        return [
            'isSuccessful' => false,
            'value' => null,
            'error' => $error,
        ];
    }
}
