<?php

declare(strict_types=1);

namespace Spart\Sdk\Internal;

/**
 * Type-strict accessors for fields of a JSON-decoded webhook envelope DTO.
 *
 * Centralizes the canonical "missing or non-string" error format and the
 * isset/is_string/non-empty contract that every envelope DTO's fromArray
 * factory needs. Prevents drift across the four EnvelopeData implementations.
 *
 * @internal
 */
final class EnvelopeFieldHelper
{
    /**
     * @param array<string,mixed> $row
     * @throws \InvalidArgumentException when the field is missing, null, not a string, or empty.
     */
    public static function requireString(array $row, string $field, string $className): string
    {
        if (!isset($row[$field]) || !is_string($row[$field]) || $row[$field] === '') {
            throw new \InvalidArgumentException("{$className}: missing or non-string {$field}");
        }
        return $row[$field];
    }

    /**
     * @param array<string,mixed> $row
     * @throws \InvalidArgumentException when the field is present but not a string.
     */
    public static function optionalString(array $row, string $field, string $className): ?string
    {
        if (!array_key_exists($field, $row) || $row[$field] === null) {
            return null;
        }
        if (!is_string($row[$field])) {
            throw new \InvalidArgumentException("{$className}: {$field} must be a string when present");
        }
        return $row[$field];
    }

    /**
     * @param array<string,mixed> $row
     * @throws \InvalidArgumentException when the field is missing or not an int.
     *
     * Strict: rejects float, bool, and string-encoded ints. We want drift in
     * the wire payload to surface here, not in arithmetic far downstream.
     */
    public static function requireInt(array $row, string $field, string $className): int
    {
        if (!isset($row[$field]) || !is_int($row[$field])) {
            throw new \InvalidArgumentException("{$className}: missing or non-int {$field}");
        }
        return $row[$field];
    }

    /**
     * @param array<string,mixed> $row
     * @throws \InvalidArgumentException when the field is missing or not a bool.
     *
     * Strict: rejects 0/1 ints, "true"/"false" strings, and null. We want
     * wire drift to surface here, not as silent type coercion downstream.
     */
    public static function requireBool(array $row, string $field, string $className): bool
    {
        if (!array_key_exists($field, $row) || !is_bool($row[$field])) {
            throw new \InvalidArgumentException("{$className}: missing or non-bool {$field}");
        }
        return $row[$field];
    }

    /**
     * @param array<string,mixed> $row
     * @throws \InvalidArgumentException when the field is missing or not numeric.
     *
     * Accepts both int and float (PHP's json_decode returns int for "25" and
     * float for "25.0"). Always returns float so consumers don't branch on
     * type. Rejects bool (which PHP would otherwise silently coerce to 0/1).
     *
     * Note: server emits decimal as a JSON number; PHP loses precision on
     * the way in (e.g. "25.00" -> 25.0). Use number_format() for display;
     * do NOT do bcmath-equivalent arithmetic on raw floats.
     */
    public static function requireNumber(array $row, string $field, string $className): float
    {
        if (!isset($row[$field]) || (!is_int($row[$field]) && !is_float($row[$field])) || is_bool($row[$field])) {
            throw new \InvalidArgumentException("{$className}: missing or non-numeric {$field}");
        }
        return (float) $row[$field];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     * @throws \InvalidArgumentException when the field is missing or not an array.
     *
     * Used for nested DTO sub-objects (e.g. `total`, `sparter`). Empty arrays
     * are accepted — PHP's empty `[]` is shape-ambiguous (both a JSON object
     * and a JSON array decode to it), so we let downstream field validation
     * surface "missing required X" if the empty case is in fact a wire bug.
     */
    public static function requireMap(array $row, string $field, string $className): array
    {
        if (!array_key_exists($field, $row) || !is_array($row[$field])) {
            throw new \InvalidArgumentException("{$className}: missing or non-array {$field}");
        }
        /** @var array<string,mixed> */
        return $row[$field];
    }

    /**
     * @param array<string,mixed> $row
     * @return list<mixed>
     * @throws \InvalidArgumentException when the field is missing or not an array.
     *
     * Used for list-shaped fields (e.g. `lineItems`). Empty lists are
     * accepted — they are a valid wire payload (e.g. an order with zero
     * line items, even if rare in practice).
     */
    public static function requireList(array $row, string $field, string $className): array
    {
        if (!array_key_exists($field, $row) || !is_array($row[$field])) {
            throw new \InvalidArgumentException("{$className}: missing or non-array {$field}");
        }
        /** @var list<mixed> */
        return array_values($row[$field]);
    }
}
