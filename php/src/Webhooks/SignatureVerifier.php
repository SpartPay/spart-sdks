<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Exceptions\SpartValidationException;

/**
 * Verifies inbound Spart webhook signatures.
 *
 * Header format: `X-Spart-Signature: t=<unix-seconds>,v1=<lowercase-hex>`
 * Signature: HMAC-SHA256 over the byte sequence `"{t}.{rawBody}"`.
 *
 * @final
 */
final class SignatureVerifier
{
    public function __construct(
        private readonly string $signingSecret,
        private readonly int $toleranceSeconds = 300,
    ) {
        if (trim($this->signingSecret) === '') {
            throw new \InvalidArgumentException('SignatureVerifier::signingSecret must not be blank.');
        }
        if ($this->toleranceSeconds < 1 || $this->toleranceSeconds > 86400) {
            throw new \InvalidArgumentException(
                'SignatureVerifier::toleranceSeconds must be between 1 and 86400 seconds (24h).'
            );
        }
    }

    /**
     * @param int|null $now Unix seconds used for the timestamp-tolerance check;
     *                      defaults to the wall clock. Injectable for deterministic tests.
     */
    public function verify(string $rawBody, string $headerValue, ?int $now = null): bool
    {
        $parsed = self::parseHeader($headerValue);
        if ($parsed === null) {
            return false;
        }
        [$t, $v1] = $parsed;

        $now ??= time();
        if (abs($now - $t) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$t}.{$rawBody}", $this->signingSecret);
        return hash_equals($expected, $v1);
    }

    /**
     * @throws SpartValidationException when the signature does not verify
     *                                  or the envelope cannot be parsed.
     */
    public function verifyAndParse(string $rawBody, string $headerValue, string $deliveryId, int $attempt): Event
    {
        if (!$this->verify($rawBody, $headerValue)) {
            throw new SpartValidationException('Spart webhook signature verification failed.');
        }
        try {
            return Event::fromJson($rawBody, deliveryId: $deliveryId, attempt: $attempt);
        } catch (\InvalidArgumentException $e) {
            throw new SpartValidationException(
                'Spart webhook envelope is invalid: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /** @return array{0:int,1:string}|null */
    public static function parseHeader(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        $t = null;
        $v1 = null;
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $raw = substr($part, 2);
                if (!ctype_digit($raw)) {
                    return null;
                }
                $t = (int) $raw;
            } elseif (str_starts_with($part, 'v1=')) {
                $v1 = substr($part, 3);
            }
        }
        if ($t === null || $v1 === null || $v1 === '') {
            return null;
        }
        return [$t, $v1];
    }
}
