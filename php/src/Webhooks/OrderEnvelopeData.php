<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookLineItem;
use Spart\Sdk\Webhooks\Models\WebhookMoney;
use Spart\Sdk\Webhooks\Models\WebhookPaymentPart;

/**
 * The `order` sub-envelope, carried inside `data.order` of order.created,
 * order.completed, order.canceled, and order.expired webhooks.
 *
 * Wire shape (JSON):
 *
 *   {
 *     shortId:       string,
 *     originalTotal: { currency: string, amount: number },
 *     finalTotal:    { currency: string, amount: number },
 *     lineItems:     [{ name: string, quantity: int }],
 *     sparter:       { fullName: string, email: string },
 *     paymentParts:  [ { ...see WebhookPaymentPart } ],   // optional
 *     sessionId:     string | null,
 *     status:        string,   // lowercased — see OrderStatus
 *     countryCode:   string,
 *     createdAt:     ISO 8601 string
 *   }
 *
 * `paymentParts` is optional: it is emitted on all order.* events by current
 * servers, but is defaulted to an empty list so payloads predating the field
 * (or replays thereof) still parse. Contacts inside it are redacted server-side.
 *
 * @final
 */
final class OrderEnvelopeData implements EnvelopeData
{
    /**
     * @param list<WebhookLineItem> $lineItems
     * @param list<WebhookPaymentPart> $paymentParts
     */
    public function __construct(
        public readonly string $shortId,
        public readonly WebhookMoney $originalTotal,
        public readonly WebhookMoney $finalTotal,
        public readonly array $lineItems,
        public readonly WebhookContact $sparter,
        public readonly ?string $sessionId,
        public readonly string $status,
        public readonly string $countryCode,
        public readonly string $createdAt,
        public readonly array $paymentParts = [],
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $originalTotal = EnvelopeFieldHelper::requireMap($row, 'originalTotal', 'OrderEnvelopeData');
        $finalTotal = EnvelopeFieldHelper::requireMap($row, 'finalTotal', 'OrderEnvelopeData');
        $lineItemRows = EnvelopeFieldHelper::requireList($row, 'lineItems', 'OrderEnvelopeData');
        $sparter = EnvelopeFieldHelper::requireMap($row, 'sparter', 'OrderEnvelopeData');
        $paymentPartRows = EnvelopeFieldHelper::optionalList($row, 'paymentParts', 'OrderEnvelopeData');

        $lineItems = [];
        foreach ($lineItemRows as $li) {
            if (!is_array($li)) {
                throw new \InvalidArgumentException('OrderEnvelopeData: lineItems entries must be objects');
            }
            $lineItems[] = WebhookLineItem::fromArray($li);
        }

        $paymentParts = [];
        foreach ($paymentPartRows as $pp) {
            if (!is_array($pp)) {
                throw new \InvalidArgumentException('OrderEnvelopeData: paymentParts entries must be objects');
            }
            $paymentParts[] = WebhookPaymentPart::fromArray($pp);
        }

        return new self(
            shortId:       EnvelopeFieldHelper::requireString($row, 'shortId', 'OrderEnvelopeData'),
            originalTotal: WebhookMoney::fromArray($originalTotal),
            finalTotal:    WebhookMoney::fromArray($finalTotal),
            lineItems:     $lineItems,
            sparter:       WebhookContact::fromArray($sparter),
            sessionId:     EnvelopeFieldHelper::optionalString($row, 'sessionId', 'OrderEnvelopeData'),
            status:        EnvelopeFieldHelper::requireString($row, 'status', 'OrderEnvelopeData'),
            countryCode:   EnvelopeFieldHelper::requireString($row, 'countryCode', 'OrderEnvelopeData'),
            createdAt:     EnvelopeFieldHelper::requireString($row, 'createdAt', 'OrderEnvelopeData'),
            paymentParts:  $paymentParts,
        );
    }
}
