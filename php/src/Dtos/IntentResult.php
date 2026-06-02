<?php

declare(strict_types=1);

namespace Spart\Sdk\Dtos;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * Result of a successful `POST /api/intents` call.
 *
 * Field names mirror the server's `CreateIntentResultDto` (`intentShortId`,
 * `checkoutUrl`). The full intent UUID is not returned by this endpoint —
 * use `intentShortId` for both URLs and follow-up reads.
 *
 * `wasIdempotentReplay` is derived from the HTTP status code: the server
 * returns 201 Created for new intents and 200 OK when an existing
 * (sessionId, payload-hash) tuple is replayed. The flag is only meaningful
 * when the caller supplied a `sessionId` on the request — without one,
 * the server cannot match a prior intent.
 *
 * @final
 */
final class IntentResult
{
    private function __construct(
        public readonly string $intentShortId,
        public readonly string $checkoutUrl,
        public readonly bool $wasIdempotentReplay,
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromArray(array $body, bool $wasReplay): self
    {
        $shortId = EnvelopeFieldHelper::requireString($body, 'intentShortId', 'IntentResult');
        $checkoutUrl = EnvelopeFieldHelper::requireString($body, 'checkoutUrl', 'IntentResult');
        return new self($shortId, $checkoutUrl, $wasReplay);
    }
}
