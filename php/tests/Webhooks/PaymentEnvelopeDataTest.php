<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookMoney;
use Spart\Sdk\Webhooks\PaymentEnvelopeData;

/**
 * Locks down the wire shape of the `payment` sub-envelope.
 *
 * Server JSON shape:
 *
 *   { orderShortId, sessionId?, paymentPartId (Guid string),
 *     amountAuthorized{currency,amount}, payee{fullName,email},
 *     authorizedAt }
 *
 * Note that this is NOT a generic "payment" payload — the only payment
 * event the server emits today is payment.authorized. There is no
 * `payment.captured` or `payment.failed` (see EventTypeTest).
 */
final class PaymentEnvelopeDataTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validRow(): array
    {
        return [
            'orderShortId'     => 'order123',
            'sessionId'        => 'wc_42',
            'paymentPartId'    => '550e8400-e29b-41d4-a716-446655440000',
            'amountAuthorized' => ['currency' => 'EUR', 'amount' => 8.33],
            'payee'            => ['fullName' => 'Bob Smith', 'email' => 'bob@example.com'],
            'authorizedAt'     => '2026-05-06T11:30:00+00:00',
        ];
    }

    public function test_happy_path(): void
    {
        $d = PaymentEnvelopeData::fromArray(self::validRow());

        self::assertSame('order123', $d->orderShortId);
        self::assertSame('wc_42', $d->sessionId);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $d->paymentPartId);
        self::assertInstanceOf(WebhookMoney::class, $d->amountAuthorized);
        self::assertSame('EUR', $d->amountAuthorized->currency);
        self::assertSame(8.33, $d->amountAuthorized->amount);
        self::assertInstanceOf(WebhookContact::class, $d->payee);
        self::assertSame('Bob Smith', $d->payee->fullName);
        self::assertSame('2026-05-06T11:30:00+00:00', $d->authorizedAt);
    }

    public function test_sessionId_null_when_absent(): void
    {
        $row = self::validRow();
        unset($row['sessionId']);
        self::assertNull(PaymentEnvelopeData::fromArray($row)->sessionId);
    }

    public function test_throws_on_missing_orderShortId(): void
    {
        $row = self::validRow();
        unset($row['orderShortId']);
        $this->expectException(\InvalidArgumentException::class);
        PaymentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_paymentPartId(): void
    {
        $row = self::validRow();
        unset($row['paymentPartId']);
        $this->expectException(\InvalidArgumentException::class);
        PaymentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_amountAuthorized(): void
    {
        $row = self::validRow();
        unset($row['amountAuthorized']);
        $this->expectException(\InvalidArgumentException::class);
        PaymentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_payee(): void
    {
        $row = self::validRow();
        unset($row['payee']);
        $this->expectException(\InvalidArgumentException::class);
        PaymentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_missing_authorizedAt(): void
    {
        $row = self::validRow();
        unset($row['authorizedAt']);
        $this->expectException(\InvalidArgumentException::class);
        PaymentEnvelopeData::fromArray($row);
    }

    public function test_throws_on_malformed_amountAuthorized(): void
    {
        $row = self::validRow();
        $row['amountAuthorized'] = ['currency' => 'EUR']; // missing amount
        $this->expectException(\InvalidArgumentException::class);
        PaymentEnvelopeData::fromArray($row);
    }
}
