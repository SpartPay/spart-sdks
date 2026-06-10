<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks\Models;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * Payee charge as it appears in webhook payloads.
 *
 * Wire shape (JSON):
 *
 *   {
 *     net:   { currency: string, amount: number },
 *     total: { currency: string, amount: number },
 *     fees:  { <feeName: string>: number }
 *   }
 *
 * `total` is the gross amount the payee is charged, `net` is what remains
 * after fees, and `fees` is the per-fee breakdown (fee name -> amount).
 *
 * Precision: amounts are floats — see {@see WebhookMoney}. Do not reconcile
 * totals from floats; use the server-provided net/total for display.
 *
 * @final
 */
final class WebhookCharge
{
    /**
     * @param array<string,float> $fees
     */
    public function __construct(
        public readonly WebhookMoney $net,
        public readonly WebhookMoney $total,
        public readonly array $fees,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $net = EnvelopeFieldHelper::requireMap($row, 'net', 'WebhookCharge');
        $total = EnvelopeFieldHelper::requireMap($row, 'total', 'WebhookCharge');
        $feeRows = EnvelopeFieldHelper::requireMap($row, 'fees', 'WebhookCharge');

        $fees = [];
        foreach ($feeRows as $name => $value) {
            // A bool is neither int nor float, so it is rejected here too —
            // no separate is_bool guard needed (and PHP would coerce it
            // silently if we cast without this check).
            if (!is_int($value) && !is_float($value)) {
                throw new \InvalidArgumentException(
                    'WebhookCharge: fee "' . (string) $name . '" must be numeric'
                );
            }
            $fees[(string) $name] = (float) $value;
        }

        return new self(
            net:   WebhookMoney::fromArray($net),
            total: WebhookMoney::fromArray($total),
            fees:  $fees,
        );
    }
}
