<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks\Models;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * LineItem value object as it appears in webhook payloads.
 *
 * Wire shape (JSON): { name: string, quantity: int }.
 *
 * Note: the WEBHOOK-side LineItem is intentionally NARROWER than
 * the REQUEST-side Spart\Sdk\Models\LineItem, which carries
 * description, unit price, image URI, etc. The webhook view only
 * carries name + quantity because that is all merchants need to
 * render an order summary post-fact.
 *
 * @final
 */
final class WebhookLineItem
{
    public function __construct(
        public readonly string $name,
        public readonly int $quantity,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            name:     EnvelopeFieldHelper::requireString($row, 'name', 'WebhookLineItem'),
            quantity: EnvelopeFieldHelper::requireInt($row, 'quantity', 'WebhookLineItem'),
        );
    }
}
