<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Internal\EnvelopeFieldHelper;

final class EnvelopeFieldHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // requireString
    // -------------------------------------------------------------------------

    public function test_requireString_returns_value_when_present_non_empty_string(): void
    {
        self::assertSame('hello', EnvelopeFieldHelper::requireString(['k' => 'hello'], 'k', 'TestDto'));
    }

    public function test_requireString_throws_when_field_absent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-string k');
        EnvelopeFieldHelper::requireString([], 'k', 'TestDto');
    }

    public function test_requireString_throws_when_field_is_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-string k');
        EnvelopeFieldHelper::requireString(['k' => null], 'k', 'TestDto');
    }

    public function test_requireString_throws_when_field_is_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-string k');
        EnvelopeFieldHelper::requireString(['k' => ''], 'k', 'TestDto');
    }

    public function test_requireString_throws_when_field_is_int(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-string k');
        EnvelopeFieldHelper::requireString(['k' => 42], 'k', 'TestDto');
    }

    public function test_requireString_throws_when_field_is_bool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-string k');
        EnvelopeFieldHelper::requireString(['k' => true], 'k', 'TestDto');
    }

    public function test_requireString_throws_when_field_is_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireString(['k' => ['nested']], 'k', 'TestDto');
    }

    // -------------------------------------------------------------------------
    // optionalString
    // -------------------------------------------------------------------------

    public function test_optionalString_returns_null_when_absent(): void
    {
        self::assertNull(EnvelopeFieldHelper::optionalString([], 'k', 'TestDto'));
    }

    public function test_optionalString_returns_null_when_explicitly_null(): void
    {
        self::assertNull(EnvelopeFieldHelper::optionalString(['k' => null], 'k', 'TestDto'));
    }

    public function test_optionalString_returns_value_when_present_string(): void
    {
        self::assertSame('hello', EnvelopeFieldHelper::optionalString(['k' => 'hello'], 'k', 'TestDto'));
    }

    public function test_optionalString_returns_empty_string_as_is(): void
    {
        // optionalString accepts empty string (semantic difference from requireString)
        self::assertSame('', EnvelopeFieldHelper::optionalString(['k' => ''], 'k', 'TestDto'));
    }

    public function test_optionalString_throws_when_present_non_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: k must be a string when present');
        EnvelopeFieldHelper::optionalString(['k' => 42], 'k', 'TestDto');
    }

    // -------------------------------------------------------------------------
    // requireInt
    // -------------------------------------------------------------------------

    public function test_requireInt_returns_value_when_present_int(): void
    {
        self::assertSame(42, EnvelopeFieldHelper::requireInt(['k' => 42], 'k', 'TestDto'));
    }

    public function test_requireInt_throws_when_field_absent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-int k');
        EnvelopeFieldHelper::requireInt([], 'k', 'TestDto');
    }

    public function test_requireInt_throws_when_field_is_string_digit(): void
    {
        // We intentionally do not coerce "42" to 42 — drift surface guard.
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireInt(['k' => '42'], 'k', 'TestDto');
    }

    public function test_requireInt_throws_when_field_is_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireInt(['k' => 1.5], 'k', 'TestDto');
    }

    public function test_requireInt_throws_when_field_is_bool(): void
    {
        // PHP would silently treat `true` as 1 in arithmetic — guard explicitly.
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireInt(['k' => true], 'k', 'TestDto');
    }

    // -------------------------------------------------------------------------
    // requireBool
    // -------------------------------------------------------------------------

    public function test_requireBool_returns_true_when_field_is_true(): void
    {
        self::assertTrue(EnvelopeFieldHelper::requireBool(['k' => true], 'k', 'TestDto'));
    }

    public function test_requireBool_returns_false_when_field_is_false(): void
    {
        self::assertFalse(EnvelopeFieldHelper::requireBool(['k' => false], 'k', 'TestDto'));
    }

    public function test_requireBool_throws_when_field_absent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-bool k');
        EnvelopeFieldHelper::requireBool([], 'k', 'TestDto');
    }

    public function test_requireBool_throws_when_field_is_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-bool k');
        EnvelopeFieldHelper::requireBool(['k' => null], 'k', 'TestDto');
    }

    public function test_requireBool_throws_when_field_is_int_1(): void
    {
        // Strict: reject 0/1 ints — JSON true/false should not arrive as ints.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-bool k');
        EnvelopeFieldHelper::requireBool(['k' => 1], 'k', 'TestDto');
    }

    public function test_requireBool_throws_when_field_is_string_true(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-bool k');
        EnvelopeFieldHelper::requireBool(['k' => 'true'], 'k', 'TestDto');
    }

    // -------------------------------------------------------------------------
    // requireNumber
    // -------------------------------------------------------------------------

    public function test_requireNumber_returns_float_when_present_float(): void
    {
        self::assertSame(1.5, EnvelopeFieldHelper::requireNumber(['k' => 1.5], 'k', 'TestDto'));
    }

    public function test_requireNumber_returns_float_when_present_int(): void
    {
        // JSON "25" decodes to int; "25.00" decodes to float — accept both,
        // always return float so consumers don't have to switch on type.
        $r = EnvelopeFieldHelper::requireNumber(['k' => 25], 'k', 'TestDto');
        self::assertSame(25.0, $r);
        self::assertIsFloat($r);
    }

    public function test_requireNumber_throws_when_field_absent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-numeric k');
        EnvelopeFieldHelper::requireNumber([], 'k', 'TestDto');
    }

    public function test_requireNumber_throws_when_field_is_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireNumber(['k' => '25.00'], 'k', 'TestDto');
    }

    public function test_requireNumber_throws_when_field_is_bool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireNumber(['k' => true], 'k', 'TestDto');
    }

    // -------------------------------------------------------------------------
    // requireMap
    // -------------------------------------------------------------------------

    public function test_requireMap_returns_value_when_present_array(): void
    {
        self::assertSame(['x' => 1], EnvelopeFieldHelper::requireMap(['k' => ['x' => 1]], 'k', 'TestDto'));
    }

    public function test_requireMap_throws_when_field_absent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-array k');
        EnvelopeFieldHelper::requireMap([], 'k', 'TestDto');
    }

    public function test_requireMap_throws_when_field_is_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireMap(['k' => null], 'k', 'TestDto');
    }

    public function test_requireMap_throws_when_field_is_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireMap(['k' => 'hello'], 'k', 'TestDto');
    }

    public function test_requireMap_accepts_empty_array(): void
    {
        self::assertSame([], EnvelopeFieldHelper::requireMap(['k' => []], 'k', 'TestDto'));
    }

    // -------------------------------------------------------------------------
    // requireList
    // -------------------------------------------------------------------------

    public function test_requireList_returns_values_for_int_keyed_array(): void
    {
        self::assertSame(
            ['a', 'b', 'c'],
            EnvelopeFieldHelper::requireList(['k' => ['a', 'b', 'c']], 'k', 'TestDto')
        );
    }

    public function test_requireList_reindexes_string_keyed_array_via_array_values(): void
    {
        // Defensive: even if a server were to emit object-shaped data where a
        // list is expected, downstream consumers loop with foreach which
        // doesn't care about keys. requireList normalizes via array_values()
        // so callers get a list<mixed> regardless.
        self::assertSame(
            ['x', 'y'],
            EnvelopeFieldHelper::requireList(['k' => ['a' => 'x', 'b' => 'y']], 'k', 'TestDto')
        );
    }

    public function test_requireList_throws_when_field_absent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: missing or non-array k');
        EnvelopeFieldHelper::requireList([], 'k', 'TestDto');
    }

    public function test_requireList_throws_when_field_is_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireList(['k' => null], 'k', 'TestDto');
    }

    public function test_requireList_throws_when_field_is_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::requireList(['k' => 'hello'], 'k', 'TestDto');
    }

    public function test_requireList_accepts_empty_array(): void
    {
        // [] is a legitimate "empty list" payload (e.g. an order with no
        // line items in a future use case) — must not throw.
        self::assertSame([], EnvelopeFieldHelper::requireList(['k' => []], 'k', 'TestDto'));
    }

    // -------------------------------------------------------------------------
    // optionalList
    // -------------------------------------------------------------------------

    public function test_optionalList_returns_empty_when_absent(): void
    {
        // Forward/backward compat: a payload predating the field (or an order
        // event without parts) must default to [], never throw.
        self::assertSame([], EnvelopeFieldHelper::optionalList([], 'k', 'TestDto'));
    }

    public function test_optionalList_returns_empty_when_null(): void
    {
        // Tolerant: an explicit null is treated the same as absent.
        self::assertSame([], EnvelopeFieldHelper::optionalList(['k' => null], 'k', 'TestDto'));
    }

    public function test_optionalList_returns_values_for_a_list(): void
    {
        self::assertSame(
            [['a' => 1], ['b' => 2]],
            EnvelopeFieldHelper::optionalList(['k' => [['a' => 1], ['b' => 2]]], 'k', 'TestDto')
        );
    }

    public function test_optionalList_accepts_empty_array(): void
    {
        self::assertSame([], EnvelopeFieldHelper::optionalList(['k' => []], 'k', 'TestDto'));
    }

    public function test_optionalList_throws_when_field_is_a_map(): void
    {
        // A JSON object decodes to an assoc array (not a list). The field is
        // contractually a JSON array — surface the drift instead of silently
        // reshaping it via array_values.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TestDto: k must be a JSON array');
        EnvelopeFieldHelper::optionalList(['k' => ['id' => 'x']], 'k', 'TestDto');
    }

    public function test_optionalList_throws_when_field_is_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::optionalList(['k' => 'hello'], 'k', 'TestDto');
    }

    public function test_optionalList_throws_when_field_is_int(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvelopeFieldHelper::optionalList(['k' => 42], 'k', 'TestDto');
    }
}
