<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookCharge;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * Locks down the wire shape of the payee charge:
 * { net{currency,amount}, total{currency,amount}, fees{<name>:number} }.
 */
final class WebhookChargeTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validRow(): array
    {
        return [
            'net'   => ['currency' => 'EUR', 'amount' => 195.00],
            'total' => ['currency' => 'EUR', 'amount' => 199.99],
            'fees'  => ['platform' => 4.99],
        ];
    }

    public function test_happy_path(): void
    {
        $c = WebhookCharge::fromArray(self::validRow());

        self::assertInstanceOf(WebhookMoney::class, $c->net);
        self::assertSame(195.0, $c->net->amount);
        self::assertSame('EUR', $c->net->currency);
        self::assertInstanceOf(WebhookMoney::class, $c->total);
        self::assertSame(199.99, $c->total->amount);
        self::assertSame(['platform' => 4.99], $c->fees);
    }

    public function test_accepts_empty_fees(): void
    {
        $row = self::validRow();
        $row['fees'] = [];
        self::assertSame([], WebhookCharge::fromArray($row)->fees);
    }

    public function test_accepts_integer_fee_value(): void
    {
        // JSON integers are valid numbers; coerced to float.
        $row = self::validRow();
        $row['fees'] = ['platform' => 5];
        self::assertSame(['platform' => 5.0], WebhookCharge::fromArray($row)->fees);
    }

    public function test_preserves_multiple_named_fees(): void
    {
        $row = self::validRow();
        $row['fees'] = ['platform' => 4.99, 'processing' => 1.50];
        self::assertSame(['platform' => 4.99, 'processing' => 1.50], WebhookCharge::fromArray($row)->fees);
    }

    public function test_throws_on_non_numeric_fee_value(): void
    {
        $row = self::validRow();
        $row['fees'] = ['platform' => 'free'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WebhookCharge: fee "platform" must be numeric');
        WebhookCharge::fromArray($row);
    }

    public function test_throws_on_bool_fee_value(): void
    {
        // is_bool guard: PHP would otherwise coerce true -> 1.0 silently.
        $row = self::validRow();
        $row['fees'] = ['platform' => true];
        $this->expectException(\InvalidArgumentException::class);
        WebhookCharge::fromArray($row);
    }

    public function test_throws_on_missing_net(): void
    {
        $row = self::validRow();
        unset($row['net']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookCharge::fromArray($row);
    }

    public function test_throws_on_missing_total(): void
    {
        $row = self::validRow();
        unset($row['total']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookCharge::fromArray($row);
    }

    public function test_throws_on_missing_fees(): void
    {
        $row = self::validRow();
        unset($row['fees']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookCharge::fromArray($row);
    }
}
