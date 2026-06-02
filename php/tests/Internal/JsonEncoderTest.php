<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Internal\JsonEncoder;
use Spart\Sdk\Models\Money;

final class JsonEncoderTest extends TestCase
{
    public function test_money_serializes_as_value_currency_object_with_unquoted_decimal(): void
    {
        $payload = ['total' => Money::fromString('25.00', 'EUR'), 'note' => 'hello'];
        $json = JsonEncoder::encode($payload);
        // Must contain literal `"value":25.00` — NOT `"25.00"` (string would 400 server-side).
        self::assertStringContainsString('"value":25.00', $json);
        self::assertStringContainsString('"currency":"EUR"', $json);
        self::assertStringContainsString('"note":"hello"', $json);
        self::assertStringNotContainsString('"value":"25.00"', $json);
    }

    public function test_money_in_nested_object_at_total_value_position(): void
    {
        // Mirrors the create-intent body shape: {total: {value: ..., currency: ...}, lineItems: [...]}
        $payload = ['total' => Money::fromString('99.99', 'USD'), 'lineItems' => []];
        $json = JsonEncoder::encode($payload);
        self::assertStringContainsString('"total":{"value":99.99,"currency":"USD"}', $json);
    }

    public function test_money_in_deeply_nested_array_is_substituted(): void
    {
        $payload = ['orders' => [['items' => [['price' => Money::fromString('5.00', 'EUR')]]]]];
        $json = JsonEncoder::encode($payload);
        self::assertStringContainsString('"price":{"value":5.00,"currency":"EUR"}', $json);
    }

    public function test_no_money_collisions_in_strings(): void
    {
        // A user-supplied string that happens to look like our sentinel must NOT be substituted.
        $payload = ['note' => '@@MONEY:0@@', 'total' => Money::fromString('1.00', 'EUR')];
        $json = JsonEncoder::encode($payload);
        self::assertStringContainsString('"note":"@@MONEY:0@@"', $json);
        self::assertStringContainsString('"value":1.00', $json);
    }

    public function test_high_precision_decimal_lexeme_is_preserved(): void
    {
        // Server's decimal accepts arbitrary precision; we must not round.
        $payload = ['total' => Money::fromString('1.23456789', 'EUR')];
        $json = JsonEncoder::encode($payload);
        self::assertStringContainsString('"value":1.23456789', $json);
    }

    public function test_negative_money_is_substituted(): void
    {
        // Money allows negative values for parity with the .NET model.
        $payload = ['amount' => Money::fromString('-25.00', 'EUR')];
        $json = JsonEncoder::encode($payload);
        self::assertStringContainsString('"value":-25.00', $json);
    }

    public function test_null_in_array_is_emitted_as_json_null(): void
    {
        $json = JsonEncoder::encode(['x' => null]);
        self::assertSame('{"x":null}', $json);
    }

    public function test_encode_rejects_unknown_object_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/stdClass/');
        JsonEncoder::encode(['x' => new \stdClass()]);
    }
}
