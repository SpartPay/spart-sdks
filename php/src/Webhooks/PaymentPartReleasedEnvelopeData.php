<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * The `payment` sub-envelope, carried inside `data.payment` of a
 * order.payment_part_released webhook.
 *
 * Wire shape (JSON):
 *
 *   {
 *     orderShortId:   string,
 *     sessionId:      string | null,
 *     paymentPartId:  string,   // Guid string
 *     amountReleased: { currency: string, amount: number },
 *     payee:          { fullName: string, email: string },  // redacted server-side; display-only PII
 *     releasedAt:     ISO 8601 string
 *   }
 *
 * Released = a previously-authorized hold was voided (order canceled/expired);
 * no funds were captured. The payee is redacted server-side but treat it as
 * display-only PII regardless.
 *
 * @final
 */
final class PaymentPartReleasedEnvelopeData implements EnvelopeData
{
    public function __construct(
        public readonly string $orderShortId,
        public readonly ?string $sessionId,
        public readonly string $paymentPartId,
        public readonly WebhookMoney $amountReleased,
        public readonly WebhookContact $payee,
        public readonly string $releasedAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $amount = EnvelopeFieldHelper::requireMap($row, 'amountReleased', 'PaymentPartReleasedEnvelopeData');
        $payee = EnvelopeFieldHelper::requireMap($row, 'payee', 'PaymentPartReleasedEnvelopeData');

        return new self(
            orderShortId:   EnvelopeFieldHelper::requireString($row, 'orderShortId', 'PaymentPartReleasedEnvelopeData'),
            sessionId:      EnvelopeFieldHelper::optionalString($row, 'sessionId', 'PaymentPartReleasedEnvelopeData'),
            paymentPartId:  EnvelopeFieldHelper::requireString($row, 'paymentPartId', 'PaymentPartReleasedEnvelopeData'),
            amountReleased: WebhookMoney::fromArray($amount),
            payee:          WebhookContact::fromArray($payee),
            releasedAt:     EnvelopeFieldHelper::requireString($row, 'releasedAt', 'PaymentPartReleasedEnvelopeData'),
        );
    }
}
