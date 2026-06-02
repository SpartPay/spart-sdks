<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookLineItem;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * The `order` sub-envelope, carried inside `data.order` of
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
 *     sessionId:     string | null,
 *     status:        string,   // lowercased — see OrderStatus
 *     countryCode:   string,
 *     createdAt:     ISO 8601 string
 *   }
 *
 * @final
 */
final class OrderEnvelopeData implements EnvelopeData
{
    /**
     * @param list<WebhookLineItem> $lineItems
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
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $originalTotal = EnvelopeFieldHelper::requireMap($row, 'originalTotal', 'OrderEnvelopeData');
        $finalTotal = EnvelopeFieldHelper::requireMap($row, 'finalTotal', 'OrderEnvelopeData');
        $lineItemRows = EnvelopeFieldHelper::requireList($row, 'lineItems', 'OrderEnvelopeData');
        $sparter = EnvelopeFieldHelper::requireMap($row, 'sparter', 'OrderEnvelopeData');

        $lineItems = [];
        foreach ($lineItemRows as $li) {
            if (!is_array($li)) {
                throw new \InvalidArgumentException('OrderEnvelopeData: lineItems entries must be objects');
            }
            $lineItems[] = WebhookLineItem::fromArray($li);
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
        );
    }
}
