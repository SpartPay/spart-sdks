<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks\Models;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * A single payment part (payee) as it appears in webhook order payloads.
 *
 * Wire shape (JSON):
 *
 *   {
 *     id:           string,        // GUID
 *     amount:       number,        // split amount value
 *     amountType:   string,        // e.g. "Percent" (PascalCase — see note)
 *     status:       string,        // lowercased, e.g. "captured"
 *     isSparter:    bool,          // true when this payee is the buyer
 *     payee:        { fullName: string, email: string },   // REDACTED server-side
 *     payeeCharge:  { net{...}, total{...}, fees{...} },
 *     authorizedAt: string | null, // ISO 8601
 *     capturedAt:   string | null,
 *     releasedAt:   string | null
 *   }
 *
 * Casing note: `amountType` is PascalCase while `status` is lowercased — this
 * asymmetry mirrors the server contract. The SDK preserves the raw strings;
 * consumers should branch on known values and tolerate unknown ones rather
 * than fail, since the server may add values over time.
 *
 * PII: `payee` is already redacted by the server (masked name + email). Never
 * log the raw values; treat them as display-only.
 *
 * @final
 */
final class WebhookPaymentPart
{
    public function __construct(
        public readonly string $id,
        public readonly float $amount,
        public readonly string $amountType,
        public readonly string $status,
        public readonly bool $isSparter,
        public readonly WebhookContact $payee,
        public readonly WebhookCharge $payeeCharge,
        public readonly ?string $authorizedAt,
        public readonly ?string $capturedAt,
        public readonly ?string $releasedAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $payee = EnvelopeFieldHelper::requireMap($row, 'payee', 'WebhookPaymentPart');
        $payeeCharge = EnvelopeFieldHelper::requireMap($row, 'payeeCharge', 'WebhookPaymentPart');

        return new self(
            id:           EnvelopeFieldHelper::requireString($row, 'id', 'WebhookPaymentPart'),
            amount:       EnvelopeFieldHelper::requireNumber($row, 'amount', 'WebhookPaymentPart'),
            amountType:   EnvelopeFieldHelper::requireString($row, 'amountType', 'WebhookPaymentPart'),
            status:       EnvelopeFieldHelper::requireString($row, 'status', 'WebhookPaymentPart'),
            isSparter:    EnvelopeFieldHelper::requireBool($row, 'isSparter', 'WebhookPaymentPart'),
            payee:        WebhookContact::fromArray($payee),
            payeeCharge:  WebhookCharge::fromArray($payeeCharge),
            authorizedAt: EnvelopeFieldHelper::optionalString($row, 'authorizedAt', 'WebhookPaymentPart'),
            capturedAt:   EnvelopeFieldHelper::optionalString($row, 'capturedAt', 'WebhookPaymentPart'),
            releasedAt:   EnvelopeFieldHelper::optionalString($row, 'releasedAt', 'WebhookPaymentPart'),
        );
    }
}
