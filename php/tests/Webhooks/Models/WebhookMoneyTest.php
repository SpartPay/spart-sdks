<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookMoney;

/**
 * Locks down the wire shape of {currency: string, amount: number}.
 *
 * The server's amount uses a decimal-precise representation; PHP's
 * json_decode loses precision because JSON numbers decode to float
 * (e.g. "25.00" -> 25.0). Consumers needing display formatting must
 * use number_format($amount, 2) — never raw float arithmetic on
 * currency values.
 */
final class WebhookMoneyTest extends TestCase
{
    public function test_happy_path_with_float_amount(): void
    {
        $m = WebhookMoney::fromArray(['currency' => 'EUR', 'amount' => 25.50]);
        self::assertSame('EUR', $m->currency);
        self::assertSame(25.50, $m->amount);
    }

    public function test_accepts_int_amount_and_casts_to_float(): void
    {
        // PHP json_decode returns int for "25" and float for "25.0" — accept both.
        $m = WebhookMoney::fromArray(['currency' => 'GBP', 'amount' => 100]);
        self::assertSame(100.0, $m->amount);
    }

    public function test_throws_on_missing_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookMoney::fromArray(['amount' => 25.00]);
    }

    public function test_throws_on_missing_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookMoney::fromArray(['currency' => 'EUR']);
    }

    public function test_throws_on_non_string_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookMoney::fromArray(['currency' => 978, 'amount' => 25.00]);
    }

    public function test_throws_on_non_numeric_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookMoney::fromArray(['currency' => 'EUR', 'amount' => 'twenty-five']);
    }

    public function test_throws_on_bool_amount(): void
    {
        // bool is technically not a JSON number but PHP can be lenient — guard against it.
        $this->expectException(\InvalidArgumentException::class);
        WebhookMoney::fromArray(['currency' => 'EUR', 'amount' => true]);
    }
}
