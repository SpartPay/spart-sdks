<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookLineItem;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * The `intent` sub-envelope, carried inside `data.intent` of an
 * intent.created webhook.
 *
 * Wire shape (JSON):
 *
 *   {
 *     shortId:     string,
 *     total:       { currency: string, amount: number },
 *     lineItems:   [{ name: string, quantity: int }],
 *     sparter:     { fullName: string, email: string },
 *     sessionId:   string | null,
 *     countryCode: string,
 *     createdAt:   ISO 8601 string,
 *     expiresOn:   ISO 8601 string
 *   }
 *
 * Notes:
 *   - sessionId is null when the merchant did not pass one when
 *     creating the intent.
 *   - There is no `intentId` on the wire — intents are identified
 *     by shortId in webhook payloads. Merchants who created the
 *     intent already hold the canonical Guid id from IntentResult.
 *   - createdAt/expiresOn are kept as raw ISO 8601 strings; consumers
 *     can parse them with DateTimeImmutable when needed. Avoiding
 *     enforced parsing here keeps timezone-quirky inputs from
 *     blowing up the dispatch pipeline before the merchant's handler
 *     sees them.
 *
 * @final
 */
final class IntentEnvelopeData implements EnvelopeData
{
    /**
     * @param list<WebhookLineItem> $lineItems
     */
    public function __construct(
        public readonly string $shortId,
        public readonly WebhookMoney $total,
        public readonly array $lineItems,
        public readonly WebhookContact $sparter,
        public readonly ?string $sessionId,
        public readonly string $countryCode,
        public readonly string $createdAt,
        public readonly string $expiresOn,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        $total = EnvelopeFieldHelper::requireMap($row, 'total', 'IntentEnvelopeData');
        $lineItemRows = EnvelopeFieldHelper::requireList($row, 'lineItems', 'IntentEnvelopeData');
        $sparter = EnvelopeFieldHelper::requireMap($row, 'sparter', 'IntentEnvelopeData');

        $lineItems = [];
        foreach ($lineItemRows as $li) {
            if (!is_array($li)) {
                throw new \InvalidArgumentException('IntentEnvelopeData: lineItems entries must be objects');
            }
            $lineItems[] = WebhookLineItem::fromArray($li);
        }

        return new self(
            shortId:     EnvelopeFieldHelper::requireString($row, 'shortId', 'IntentEnvelopeData'),
            total:       WebhookMoney::fromArray($total),
            lineItems:   $lineItems,
            sparter:     WebhookContact::fromArray($sparter),
            sessionId:   EnvelopeFieldHelper::optionalString($row, 'sessionId', 'IntentEnvelopeData'),
            countryCode: EnvelopeFieldHelper::requireString($row, 'countryCode', 'IntentEnvelopeData'),
            createdAt:   EnvelopeFieldHelper::requireString($row, 'createdAt', 'IntentEnvelopeData'),
            expiresOn:   EnvelopeFieldHelper::requireString($row, 'expiresOn', 'IntentEnvelopeData'),
        );
    }
}
