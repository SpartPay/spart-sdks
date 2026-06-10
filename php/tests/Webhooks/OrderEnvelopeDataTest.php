<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookLineItem;
use Spart\Sdk\Webhooks\Models\WebhookMoney;
use Spart\Sdk\Webhooks\Models\WebhookPaymentPart;
use Spart\Sdk\Webhooks\OrderEnvelopeData;
use Spart\Sdk\Webhooks\OrderStatus;

/**
 * Locks down the wire shape of the `order` sub-envelope.
 *
 * Server JSON shape:
 *
 *   { shortId, originalTotal{currency,amount}, finalTotal{currency,amount},
 *     lineItems[{name,quantity}], sparter{fullName,email}, sessionId?,
 *     status (lowercased), countryCode, createdAt }
 *
 * The `status` field is the lowercased enum name emitted by the
 * server — see OrderStatusTest.
 */
final class OrderEnvelopeDataTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validRow(): array
    {
        return [
            'shortId'       => 'order123',
            'originalTotal' => ['currency' => 'EUR', 'amount' => 30.00],
            'finalTotal'    => ['currency' => 'EUR', 'amount' => 25.00],
            'lineItems'     => [['name' => 'Widget', 'quantity' => 2]],
            'sparter'       => ['fullName' => 'Alice Smith', 'email' => 'alice@example.com'],
            'sessionId'     => 'wc_42',
            'status'        => OrderStatus::COMPLETED,
            'countryCode'   => 'IT',
            'createdAt'     => '2026-05-06T11:00:00+00:00',
        ];
    }

    public function test_happy_path(): void
    {
        $d = OrderEnvelopeData::fromArray(self::validRow());

        self::assertSame('order123', $d->shortId);
        self::assertInstanceOf(WebhookMoney::class, $d->originalTotal);
        self::assertSame(30.0, $d->originalTotal->amount);
        self::assertInstanceOf(WebhookMoney::class, $d->finalTotal);
        self::assertSame(25.0, $d->finalTotal->amount);
        self::assertCount(1, $d->lineItems);
        self::assertContainsOnlyInstancesOf(WebhookLineItem::class, $d->lineItems);
        self::assertInstanceOf(WebhookContact::class, $d->sparter);
        self::assertSame('wc_42', $d->sessionId);
        self::assertSame('completed', $d->status);
        self::assertSame('IT', $d->countryCode);
        self::assertSame('2026-05-06T11:00:00+00:00', $d->createdAt);
    }

    public function test_status_canceled_uses_single_l(): void
    {
        // Drift guard: the wire string is "canceled" (US), not "cancelled".
        $row = self::validRow();
        $row['status'] = 'canceled';
        $d = OrderEnvelopeData::fromArray($row);
        self::assertSame(OrderStatus::CANCELED, $d->status);
    }

    public function test_status_allpaymentsauthorized_no_separator(): void
    {
        // Drift guard: server lowercases PascalCase into one literal token,
        // no underscores or dashes. WC plugin code that splits on "_" would
        // misparse this — the constant exists exactly to prevent that.
        $row = self::validRow();
        $row['status'] = 'allpaymentsauthorized';
        $d = OrderEnvelopeData::fromArray($row);
        self::assertSame(OrderStatus::ALL_PAYMENTS_AUTHORIZED, $d->status);
    }

    public function test_sessionId_null_when_absent(): void
    {
        $row = self::validRow();
        unset($row['sessionId']);
        self::assertNull(OrderEnvelopeData::fromArray($row)->sessionId);
    }

    public function test_throws_on_missing_shortId(): void
    {
        $row = self::validRow();
        unset($row['shortId']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_originalTotal(): void
    {
        $row = self::validRow();
        unset($row['originalTotal']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_finalTotal(): void
    {
        $row = self::validRow();
        unset($row['finalTotal']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_status(): void
    {
        $row = self::validRow();
        unset($row['status']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_countryCode(): void
    {
        $row = self::validRow();
        unset($row['countryCode']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_createdAt(): void
    {
        $row = self::validRow();
        unset($row['createdAt']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_sparter(): void
    {
        $row = self::validRow();
        unset($row['sparter']);
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_unknown_status_string_is_passed_through(): void
    {
        // Forward-compat: an unknown but well-formed status string must NOT
        // throw — that would break the WC plugin against a future server
        // release that adds a new status. The plugin can branch on
        // OrderStatus::isKnown() and ignore non-canonical values.
        $row = self::validRow();
        $row['status'] = 'someunknownfuturestatus';
        $d = OrderEnvelopeData::fromArray($row);
        self::assertSame('someunknownfuturestatus', $d->status);
        self::assertFalse(OrderStatus::isKnown($d->status));
    }

    // -------------------------------------------------------------------------
    // paymentParts (payees)
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private static function validPart(): array
    {
        return [
            'id'          => '11111111-1111-1111-1111-111111111111',
            'amount'      => 100,
            'amountType'  => 'Percent',
            'status'      => 'captured',
            'isSparter'   => true,
            'payee'       => ['fullName' => 'Alice S', 'email' => 'a****e@e******le.com'],
            'payeeCharge' => [
                'net'   => ['currency' => 'EUR', 'amount' => 195.00],
                'total' => ['currency' => 'EUR', 'amount' => 199.99],
                'fees'  => ['platform' => 4.99],
            ],
            'authorizedAt' => '2026-06-09T00:15:16+00:00',
            'capturedAt'   => '2026-06-09T00:16:00+00:00',
            'releasedAt'   => null,
        ];
    }

    public function test_payment_parts_default_to_empty_when_absent(): void
    {
        // Backward compat: payloads predating the field (or order events that
        // carry no parts) must parse cleanly with an empty list.
        $row = self::validRow();
        self::assertArrayNotHasKey('paymentParts', $row);
        self::assertSame([], OrderEnvelopeData::fromArray($row)->paymentParts);
    }

    public function test_payment_parts_default_to_empty_when_null(): void
    {
        $row = self::validRow();
        $row['paymentParts'] = null;
        self::assertSame([], OrderEnvelopeData::fromArray($row)->paymentParts);
    }

    public function test_payment_parts_default_to_empty_when_empty_array(): void
    {
        $row = self::validRow();
        $row['paymentParts'] = [];
        self::assertSame([], OrderEnvelopeData::fromArray($row)->paymentParts);
    }

    public function test_payment_parts_are_parsed(): void
    {
        $row = self::validRow();
        $row['paymentParts'] = [self::validPart(), self::validPart()];

        $d = OrderEnvelopeData::fromArray($row);

        self::assertCount(2, $d->paymentParts);
        self::assertContainsOnlyInstancesOf(WebhookPaymentPart::class, $d->paymentParts);
        self::assertSame('Percent', $d->paymentParts[0]->amountType);
        self::assertSame('captured', $d->paymentParts[0]->status);
        self::assertTrue($d->paymentParts[0]->isSparter);
        self::assertSame(199.99, $d->paymentParts[0]->payeeCharge->total->amount);
        self::assertSame('Alice S', $d->paymentParts[0]->payee->fullName);
    }

    public function test_throws_when_payment_parts_is_an_object(): void
    {
        // A JSON object (assoc array) is not a list — surface the drift.
        $row = self::validRow();
        $row['paymentParts'] = ['id' => 'x'];
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_when_a_payment_part_entry_is_not_an_object(): void
    {
        $row = self::validRow();
        $row['paymentParts'] = ['not-an-object'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OrderEnvelopeData: paymentParts entries must be objects');
        OrderEnvelopeData::fromArray($row);
    }

    public function test_throws_when_a_payment_part_entry_is_malformed(): void
    {
        $row = self::validRow();
        $part = self::validPart();
        unset($part['payeeCharge']);
        $row['paymentParts'] = [$part];
        $this->expectException(\InvalidArgumentException::class);
        OrderEnvelopeData::fromArray($row);
    }
}
