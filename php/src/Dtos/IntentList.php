<?php

declare(strict_types=1);

namespace Spart\Sdk\Dtos;

/**
 * Page of intents returned by `GET /api/intents` — mirrors the server's
 * `PagedCollection<IntentDetailsDto>` wire shape:
 *
 *   `items`              list<IntentDetails>
 *   `page`               1-indexed page number actually returned
 *   `requestedPageSize`  the page size from the request (or its clamped value)
 *   `currentPageSize`    `count(items)` — convenience (server-computed)
 *   `totalPagesCount`    `ceil(totalItemsCount / requestedPageSize)`
 *   `totalItemsCount`    rows matching the query across all pages
 *
 * The server clamps `page` to >=1 and `pageSize` to 1..100 BEFORE running
 * the query, so callers should NOT rely on receiving back the values
 * they sent — always inspect the returned `page` / `requestedPageSize`.
 *
 * @final
 */
final class IntentList
{
    /** @param list<IntentDetails> $items */
    private function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $requestedPageSize,
        public readonly int $currentPageSize,
        public readonly int $totalPagesCount,
        public readonly int $totalItemsCount,
    ) {
    }

    /** @param array<string,mixed> $body */
    public static function fromArray(array $body): self
    {
        if (!isset($body['items']) || !is_array($body['items'])) {
            throw new \InvalidArgumentException('IntentList: missing or non-array items.');
        }

        $items = [];
        foreach ($body['items'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('IntentList.items: each entry must be an object.');
            }
            $items[] = IntentDetails::fromArray($row);
        }

        return new self(
            items: $items,
            page: self::requireInt($body, 'page'),
            requestedPageSize: self::requireInt($body, 'requestedPageSize'),
            currentPageSize: self::requireInt($body, 'currentPageSize'),
            totalPagesCount: self::requireInt($body, 'totalPagesCount'),
            totalItemsCount: self::requireInt($body, 'totalItemsCount'),
        );
    }

    /** @param array<string,mixed> $body */
    private static function requireInt(array $body, string $field): int
    {
        if (!array_key_exists($field, $body) || !is_int($body[$field])) {
            throw new \InvalidArgumentException("IntentList: missing or non-integer {$field}.");
        }
        return $body[$field];
    }
}
