<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\IntentEnvelopeData;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookLineItem;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * Locks down the wire shape of the `intent` sub-envelope.
 *
 * Server JSON shape:
 *
 *   { shortId, total{currency,amount}, lineItems[{name,quantity}],
 *     sparter{fullName,email}, sessionId?, countryCode,
 *     createdAt, expiresOn }
 *
 * Notes:
 *   - sessionId is nullable: server emits "sessionId": null when not set.
 *   - createdAt/expiresOn are kept as raw ISO 8601 strings; consumers
 *     can parse them with DateTimeImmutable when needed (no enforced
 *     parsing, so timezone-quirky inputs don't blow up the dispatch
 *     pipeline before the merchant's handler sees them).
 *   - There is no `intentId` on the wire — intents are identified by
 *     shortId in webhooks. The merchant who created the intent already
 *     has the full Guid id from IntentResult.
 */
final class IntentEnvelopeDataTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validRow(): array
    {
        return [
            'shortId'     => 'abcd1234',
            'total'       => ['currency' => 'EUR', 'amount' => 25.00],
            'lineItems'   => [
                ['name' => 'Widget', 'quantity' => 2],
                ['name' => 'Gizmo', 'quantity' => 1],
            ],
            'sparter'     => ['fullName' => 'Alice Smith', 'email' => 'alice@example.com'],
            'sessionId'   => 'wc_42',
            'countryCode' => 'IT',
            'createdAt'   => '2026-05-06T10:00:00+00:00',
            'expiresOn'   => '2026-05-13T10:00:00+00:00',
        ];
    }

    public function test_happy_path(): void
    {
        $d = IntentEnvelopeData::fromArray(self::validRow());

        self::assertSame('abcd1234', $d->shortId);
        self::assertInstanceOf(WebhookMoney::class, $d->total);
        self::assertSame('EUR', $d->total->currency);
        self::assertSame(25.0, $d->total->amount);

        self::assertCount(2, $d->lineItems);
        self::assertContainsOnlyInstancesOf(WebhookLineItem::class, $d->lineItems);
        self::assertSame('Widget', $d->lineItems[0]->name);
        self::assertSame(2, $d->lineItems[0]->quantity);

        self::assertInstanceOf(WebhookContact::class, $d->sparter);
        self::assertSame('Alice Smith', $d->sparter->fullName);
        self::assertSame('alice@example.com', $d->sparter->email);

        self::assertSame('wc_42', $d->sessionId);
        self::assertSame('IT', $d->countryCode);
        self::assertSame('2026-05-06T10:00:00+00:00', $d->createdAt);
        self::assertSame('2026-05-13T10:00:00+00:00', $d->expiresOn);
    }

    public function test_sessionId_null_when_absent(): void
    {
        $row = self::validRow();
        unset($row['sessionId']);
        $d = IntentEnvelopeData::fromArray($row);
        self::assertNull($d->sessionId);
    }

    public function test_sessionId_null_when_explicitly_null(): void
    {
        // Server emits "sessionId": null on the wire when not set on the intent.
        $row = self::validRow();
        $row['sessionId'] = null;
        $d = IntentEnvelopeData::fromArray($row);
        self::assertNull($d->sessionId);
    }

    public function test_empty_line_items_array_is_accepted(): void
    {
        // Server may emit an empty list (no items) — must parse cleanly,
        // not be misinterpreted as missing.
        $row = self::validRow();
        $row['lineItems'] = [];
        $d = IntentEnvelopeData::fromArray($row);
        self::assertSame([], $d->lineItems);
    }

    public function test_throws_on_missing_shortId(): void
    {
        $row = self::validRow();
        unset($row['shortId']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_total(): void
    {
        $row = self::validRow();
        unset($row['total']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_lineItems(): void
    {
        $row = self::validRow();
        unset($row['lineItems']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_sparter(): void
    {
        $row = self::validRow();
        unset($row['sparter']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_countryCode(): void
    {
        $row = self::validRow();
        unset($row['countryCode']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_createdAt(): void
    {
        $row = self::validRow();
        unset($row['createdAt']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_expiresOn(): void
    {
        $row = self::validRow();
        unset($row['expiresOn']);
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_malformed_total(): void
    {
        $row = self::validRow();
        $row['total'] = ['currency' => 'EUR']; // missing amount
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_non_array_lineItems(): void
    {
        $row = self::validRow();
        $row['lineItems'] = 'not an array';
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_malformed_line_item(): void
    {
        $row = self::validRow();
        $row['lineItems'] = [['name' => 'Widget']]; // missing quantity
        $this->expectException(\InvalidArgumentException::class);
        IntentEnvelopeData::fromArray($row);
    }
}
