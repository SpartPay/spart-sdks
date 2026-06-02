<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * The `test` sub-envelope, carried inside `data.test` of a
 * webhook.test event ("send a test webhook" merchant action).
 *
 * Wire shape (JSON):
 *
 *   { merchantAppName: string, sentAt: ISO 8601 string }
 *
 * The earlier SDK draft assumed a `nonce` field — that does not exist
 * server-side. Test-event uniqueness is provided by the outer envelope
 * id (Event::$id) and the per-attempt deliveryId (Event::$deliveryId).
 *
 * @final
 */
final class TestEnvelopeData implements EnvelopeData
{
    public function __construct(
        public readonly string $merchantAppName,
        public readonly string $sentAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            merchantAppName: EnvelopeFieldHelper::requireString($row, 'merchantAppName', 'TestEnvelopeData'),
            sentAt:          EnvelopeFieldHelper::requireString($row, 'sentAt', 'TestEnvelopeData'),
        );
    }
}
