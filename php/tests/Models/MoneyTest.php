<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Models\Money;

final class MoneyTest extends TestCase
{
    public function test_fromString_keeps_lexeme_exact_and_normalises_currency(): void
    {
        $m = Money::fromString('25.00', 'eur');
        self::assertSame('25.00', $m->value);
        self::assertSame('EUR', $m->currency);
    }

    public function test_fromString_rejects_non_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('25,00', 'EUR');
    }

    public function test_fromString_rejects_empty_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('', 'EUR');
    }

    public function test_fromString_accepts_more_than_two_decimals(): void
    {
        // Server's decimal accepts arbitrary precision; SDK must not over-restrict.
        self::assertSame('1.234', Money::fromString('1.234', 'EUR')->value);
        self::assertSame('1.23456789', Money::fromString('1.23456789', 'EUR')->value);
    }

    public function test_fromString_rejects_leading_zeros(): void
    {
        // "01.23" would emit invalid JSON after substitution.
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('01.23', 'EUR');
    }

    public function test_fromString_rejects_leading_plus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('+1.00', 'EUR');
    }

    public function test_fromString_rejects_trailing_decimal_point(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('1.', 'EUR');
    }

    public function test_fromString_rejects_leading_decimal_point(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('.5', 'EUR');
    }

    public function test_fromString_accepts_zero_decimals(): void
    {
        self::assertSame('25', Money::fromString('25', 'EUR')->value);
    }

    public function test_fromString_accepts_one_decimal(): void
    {
        self::assertSame('25.5', Money::fromString('25.5', 'EUR')->value);
    }

    public function test_fromString_accepts_zero(): void
    {
        // Money allows zero (e.g. promo line items); endpoint validators may forbid it.
        self::assertSame('0', Money::fromString('0', 'EUR')->value);
        self::assertSame('0.00', Money::fromString('0.00', 'EUR')->value);
    }

    public function test_fromMinorUnits_eur(): void
    {
        $m = Money::fromMinorUnits(2500, 2, 'EUR');
        self::assertSame('25.00', $m->value);
        self::assertSame('EUR', $m->currency);
    }

    public function test_fromMinorUnits_jpy_zero_decimals(): void
    {
        $m = Money::fromMinorUnits(100, 0, 'jpy');
        self::assertSame('100', $m->value);
        self::assertSame('JPY', $m->currency);
    }

    public function test_fromMinorUnits_negative_eur(): void
    {
        self::assertSame('-25.00', Money::fromMinorUnits(-2500, 2, 'EUR')->value);
    }

    public function test_fromMinorUnits_negative_with_nonzero_fraction(): void
    {
        self::assertSame('-25.43', Money::fromMinorUnits(-2543, 2, 'EUR')->value);
    }

    public function test_fromString_rejects_non_iso_currency_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('1.00', 'EU');
    }

    public function test_fromString_rejects_currency_with_digits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString('1.00', 'EU1');
    }

    public function test_fromMinorUnits_rejects_too_many_decimals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromMinorUnits(100, 9, 'EUR');
    }
}
