<?php

declare(strict_types=1);

namespace Spart\Sdk\Dtos;

use Spart\Sdk\Internal\EnvelopeFieldHelper;
use Spart\Sdk\Models\LineItem;
use Spart\Sdk\Models\Link;
use Spart\Sdk\Models\Money;

/**
 * Result of `GET /api/intents/{shortId}` — the full read-side projection
 * of an intent.
 *
 * Field names mirror the server's `IntentDetailsDto`: `shortId` (NOT `id`),
 * `total` (a Money value), `lineItems`, `createdAt` (ISO-8601), `orderId`
 * (the short id of the resulting order, or null while the intent is still
 * pending), `sessionId` (the merchant-supplied idempotency-friendly key),
 * `isCompleted` (true once an order exists), and `links` (a list of named
 * URLs — the server emits one link, either `Checkout` while pending or
 * `OrderDetails` once completed).
 *
 * `total.value` is a decimal lexeme (e.g. `"100.00"`) preserved verbatim
 * from the server response — see {@see Money} for why precision-safe
 * decimal-string handling matters.
 *
 * `lineItems` are pure descriptive metadata (no per-item prices); the
 * server does not reconcile them against `total`.
 *
 * @final
 */
final class IntentDetails
{
    /**
     * @param list<LineItem> $lineItems
     * @param list<Link> $links
     */
    private function __construct(
        public readonly string $shortId,
        public readonly Money $total,
        public readonly array $lineItems,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $orderId,
        public readonly ?string $sessionId,
        public readonly bool $isCompleted,
        public readonly array $links,
    ) {
    }

    /** @param array<string,mixed> $body */
    public static function fromArray(array $body): self
    {
        $shortId = EnvelopeFieldHelper::requireString($body, 'shortId', 'IntentDetails');
        $createdAtRaw = EnvelopeFieldHelper::requireString($body, 'createdAt', 'IntentDetails');

        try {
            $createdAt = new \DateTimeImmutable($createdAtRaw);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                "IntentDetails: createdAt is not a valid ISO-8601 timestamp ('{$createdAtRaw}').",
                0,
                $e,
            );
        }

        if (!isset($body['total']) || !is_array($body['total'])) {
            throw new \InvalidArgumentException('IntentDetails: missing or non-object total.');
        }
        $total = self::parseMoney($body['total']);

        if (!isset($body['lineItems']) || !is_array($body['lineItems'])) {
            throw new \InvalidArgumentException('IntentDetails: missing or non-array lineItems.');
        }
        $lineItems = self::parseLineItems($body['lineItems']);

        if (!isset($body['links']) || !is_array($body['links'])) {
            throw new \InvalidArgumentException('IntentDetails: missing or non-array links.');
        }
        $links = self::parseLinks($body['links']);

        $isCompleted = isset($body['isCompleted']) && $body['isCompleted'] === true;

        return new self(
            shortId: $shortId,
            total: $total,
            lineItems: $lineItems,
            createdAt: $createdAt,
            orderId: EnvelopeFieldHelper::optionalString($body, 'orderId', 'IntentDetails'),
            sessionId: EnvelopeFieldHelper::optionalString($body, 'sessionId', 'IntentDetails'),
            isCompleted: $isCompleted,
            links: $links,
        );
    }

    /** @param array<string,mixed> $row */
    private static function parseMoney(array $row): Money
    {
        $value = self::extractMoneyValueLexeme($row, 'IntentDetails.total');
        $currency = EnvelopeFieldHelper::requireString($row, 'currency', 'IntentDetails.total');
        return Money::fromString($value, $currency);
    }

    /**
     * Reads the `value` field of a server-emitted MoneyDto and returns it
     * as a JSON-number-safe decimal lexeme (no scientific notation, no
     * trailing zeros). The server sends `value` as a C# `decimal` which
     * System.Text.Json renders as an unquoted JSON number — `json_decode`
     * delivers it as `int|float` to PHP, NOT a string.
     *
     * Precision note: round-tripping a server-side `decimal` through PHP
     * is bounded by IEEE 754 float64 (~15-17 significant digits). For
     * normal payment amounts (any currency, any reasonable order total)
     * the conversion is exact. Currencies with very high decimal counts
     * combined with very large totals could in theory lose precision —
     * the SDK does NOT detect or warn about this.
     *
     * @param array<string,mixed> $row
     */
    private static function extractMoneyValueLexeme(array $row, string $context): string
    {
        if (!array_key_exists('value', $row)) {
            throw new \InvalidArgumentException("{$context}: missing value");
        }
        $raw = $row['value'];
        if (is_string($raw)) {
            if ($raw === '') {
                throw new \InvalidArgumentException("{$context}: value must not be empty");
            }
            return $raw;
        }
        if (is_int($raw)) {
            return (string) $raw;
        }
        if (is_float($raw)) {
            if (!is_finite($raw)) {
                throw new \InvalidArgumentException(
                    "{$context}: value must be finite (got " . var_export($raw, true) . ')',
                );
            }
            // %F (capital F) always emits fixed-point notation — never
            // scientific — and 10 fractional digits is plenty for any
            // currency the server actually accepts. Trim trailing zeros
            // and a trailing dot so Money::fromString sees a clean lexeme.
            $formatted = sprintf('%.10F', $raw);
            $trimmed = rtrim(rtrim($formatted, '0'), '.');
            return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
        }
        throw new \InvalidArgumentException(
            "{$context}: value must be a JSON number or string (got " . gettype($raw) . ')',
        );
    }

    /**
     * @param array<int|string,mixed> $rows
     * @return list<LineItem>
     */
    private static function parseLineItems(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('IntentDetails.lineItems: each entry must be an object.');
            }
            $name = EnvelopeFieldHelper::requireString($row, 'name', 'IntentDetails.lineItem');
            $quantity = $row['quantity'] ?? null;
            if (!is_int($quantity)) {
                throw new \InvalidArgumentException('IntentDetails.lineItem: quantity must be an integer.');
            }
            $out[] = new LineItem(
                name: $name,
                quantity: $quantity,
                description: EnvelopeFieldHelper::optionalString($row, 'description', 'IntentDetails.lineItem'),
                imageUri: EnvelopeFieldHelper::optionalString($row, 'imageUri', 'IntentDetails.lineItem'),
            );
        }
        return $out;
    }

    /**
     * @param array<int|string,mixed> $rows
     * @return list<Link>
     */
    private static function parseLinks(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('IntentDetails.links: each entry must be an object.');
            }
            $out[] = new Link(
                name: EnvelopeFieldHelper::requireString($row, 'name', 'IntentDetails.link'),
                url: EnvelopeFieldHelper::requireString($row, 'url', 'IntentDetails.link'),
            );
        }
        return $out;
    }
}
