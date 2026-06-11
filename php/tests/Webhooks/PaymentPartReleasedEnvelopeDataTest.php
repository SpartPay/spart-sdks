<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookMoney;
use Spart\Sdk\Webhooks\PaymentPartReleasedEnvelopeData;

final class PaymentPartReleasedEnvelopeDataTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validRow(): array
    {
        return [
            'orderShortId'  => 'order123',
            'sessionId'     => 'wc_42',
            'paymentPartId' => '550e8400-e29b-41d4-a716-446655440000',
            'amountReleased' => ['currency' => 'EUR', 'amount' => 8.33],
            'payee'         => ['fullName' => 'Bob Smith', 'email' => 'bob@example.com'],
            'releasedAt'    => '2026-05-06T11:30:00+00:00',
        ];
    }

    public function test_happy_path(): void
    {
        $d = PaymentPartReleasedEnvelopeData::fromArray(self::validRow());
        self::assertSame('order123', $d->orderShortId);
        self::assertSame('wc_42', $d->sessionId);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $d->paymentPartId);
        self::assertInstanceOf(WebhookMoney::class, $d->amountReleased);
        self::assertSame('EUR', $d->amountReleased->currency);
        self::assertSame(8.33, $d->amountReleased->amount);
        self::assertInstanceOf(WebhookContact::class, $d->payee);
        self::assertSame('Bob Smith', $d->payee->fullName);
        self::assertSame('2026-05-06T11:30:00+00:00', $d->releasedAt);
    }

    public function test_sessionId_null_when_absent(): void
    {
        $row = self::validRow();
        unset($row['sessionId']);
        self::assertNull(PaymentPartReleasedEnvelopeData::fromArray($row)->sessionId);
    }

    /** @dataProvider requiredKeys */
    public function test_throws_on_missing_required_key(string $key): void
    {
        $row = self::validRow();
        unset($row[$key]);
        $this->expectException(\InvalidArgumentException::class);
        PaymentPartReleasedEnvelopeData::fromArray($row);
    }

    /** @return iterable<string,array{0:string}> */
    public static function requiredKeys(): iterable
    {
        yield 'orderShortId' => ['orderShortId'];
        yield 'paymentPartId' => ['paymentPartId'];
        yield 'amountReleased' => ['amountReleased'];
        yield 'payee' => ['payee'];
        yield 'releasedAt' => ['releasedAt'];
    }

    public function test_throws_on_malformed_amountReleased(): void
    {
        $row = self::validRow();
        $row['amountReleased'] = ['currency' => 'EUR'];
        $this->expectException(\InvalidArgumentException::class);
        PaymentPartReleasedEnvelopeData::fromArray($row);
    }
}
