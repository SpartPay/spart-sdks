<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks\Models;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * Money value object as it appears in webhook payloads.
 *
 * Wire shape (JSON): { currency: string, amount: number }.
 *
 * IMPORTANT — precision: the server's `amount` uses a decimal-precise
 * representation, serialized as a JSON number. PHP's json_decode
 * returns float for any value with a decimal point, which means
 * trailing-zero precision is lost on the way in (e.g. "25.00" -> 25.0).
 * For display, always use number_format($amount, 2). Avoid float
 * arithmetic for currency math; route through the server.
 *
 * @final
 */
final class WebhookMoney
{
    public function __construct(
        public readonly string $currency,
        public readonly float $amount,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            currency: EnvelopeFieldHelper::requireString($row, 'currency', 'WebhookMoney'),
            amount:   EnvelopeFieldHelper::requireNumber($row, 'amount', 'WebhookMoney'),
        );
    }
}
