<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * The `payment` sub-envelope, carried inside `data.payment` of a
 * payment.authorized webhook.
 *
 * Wire shape (JSON):
 *
 *   {
 *     orderShortId:     string,
 *     sessionId:        string | null,
 *     paymentPartId:    string,   // Guid string
 *     amountAuthorized: { currency: string, amount: number },
 *     payee:            { fullName: string, email: string },
 *     authorizedAt:     ISO 8601 string
 *   }
 *
 * Note: the only payment event the server emits today is
 * payment.authorized. There is no payment.captured or payment.failed
 * — see EventTypeTest. If those are ever added, they would carry
 * different envelope shapes and would need their own DTO.
 *
 * @final
 */
final class PaymentEnvelopeData implements EnvelopeData
{
    public function __construct(
        public readonly string $orderShortId,
        public readonly ?string $sessionId,
        public readonly string $paymentPartId,
        public readonly WebhookMoney $amountAuthorized,
        public readonly WebhookContact $payee,
        public readonly string $authorizedAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $amount = EnvelopeFieldHelper::requireMap($row, 'amountAuthorized', 'PaymentEnvelopeData');
        $payee = EnvelopeFieldHelper::requireMap($row, 'payee', 'PaymentEnvelopeData');

        return new self(
            orderShortId:     EnvelopeFieldHelper::requireString($row, 'orderShortId', 'PaymentEnvelopeData'),
            sessionId:        EnvelopeFieldHelper::optionalString($row, 'sessionId', 'PaymentEnvelopeData'),
            paymentPartId:    EnvelopeFieldHelper::requireString($row, 'paymentPartId', 'PaymentEnvelopeData'),
            amountAuthorized: WebhookMoney::fromArray($amount),
            payee:            WebhookContact::fromArray($payee),
            authorizedAt:     EnvelopeFieldHelper::requireString($row, 'authorizedAt', 'PaymentEnvelopeData'),
        );
    }
}
