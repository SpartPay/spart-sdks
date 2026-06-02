<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks\Models;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * Contact value object as it appears in webhook payloads.
 *
 * Wire shape (JSON): { fullName: string, email: string }.
 *
 * The server composes fullName from first+last (falling back to
 * email when both are blank). The SDK does not need to replicate
 * that policy; it just consumes the result.
 *
 * @final
 */
final class WebhookContact
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $email,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            fullName: EnvelopeFieldHelper::requireString($row, 'fullName', 'WebhookContact'),
            email:    EnvelopeFieldHelper::requireString($row, 'email', 'WebhookContact'),
        );
    }
}
