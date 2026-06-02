<?php

declare(strict_types=1);

namespace Spart\Sdk\Models;

/**
 * Decimal-string backed monetary value with currency.
 *
 * The exact lexeme passed to {@see fromString} is preserved so that the
 * server-side `decimal` deserialiser receives the same digits with no
 * precision loss. The lexeme must be a JSON-number-safe representation:
 * no leading zeros (except for `0`/`0.x`), no leading `+`, no trailing
 * decimal point. {@see Spart\Sdk\Internal\JsonEncoder} substitutes the
 * lexeme into the JSON body unquoted because the server's JSON pipeline
 * does not coerce strings to decimals.
 *
 * The currency must be a 3-letter ISO 4217 code (e.g. `EUR`, `USD`).
 * It is normalised to upper-case.
 *
 * `Money` itself permits negative values for parity with the .NET model.
 * Endpoints that disallow negative or zero amounts
 * (such as create-intent `total`) enforce that constraint at the call site.
 *
 * @final
 */
final class Money
{
    private function __construct(
        public readonly string $value,
        public readonly string $currency,
    ) {
    }

    public static function fromString(string $value, string $currency): self
    {
        if ($value === '' || preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $value) !== 1) {
            throw new \InvalidArgumentException(
                "Money::fromString expects a JSON-number-safe decimal lexeme " .
                "(no leading zeros, no leading '+', no trailing '.'), got '{$value}'."
            );
        }
        return new self($value, self::normaliseCurrency($currency));
    }

    public static function fromMinorUnits(int $minor, int $decimals, string $currency): self
    {
        if ($decimals < 0 || $decimals > 8) {
            throw new \InvalidArgumentException(
                'Money::fromMinorUnits expects 0..8 decimals.'
            );
        }
        $normalised = self::normaliseCurrency($currency);
        if ($decimals === 0) {
            return new self((string) $minor, $normalised);
        }
        $divisor = 10 ** $decimals;
        $negative = $minor < 0;
        $abs = abs($minor);
        $whole = intdiv($abs, $divisor);
        $frac = $abs % $divisor;
        $lexeme = sprintf('%d.%0' . $decimals . 'd', $whole, $frac);
        return new self($negative ? '-' . $lexeme : $lexeme, $normalised);
    }

    private static function normaliseCurrency(string $currency): string
    {
        if (preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException(
                "Money currency must be a 3-letter ISO 4217 code, got '{$currency}'."
            );
        }
        return strtoupper($currency);
    }
}
