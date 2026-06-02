<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Dtos;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\IntentList;

final class IntentListTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function intentRow(string $shortId, string $sessionId): array
    {
        return [
            'shortId' => $shortId,
            'total' => ['value' => '100.00', 'currency' => 'CAD'],
            'lineItems' => [['name' => 'Widget', 'quantity' => 1]],
            'createdAt' => '2026-05-11T12:34:56+00:00',
            'orderId' => null,
            'sessionId' => $sessionId,
            'isCompleted' => false,
            'links' => [['name' => 'Checkout', 'url' => "https://example.test/c/{$shortId}"]],
        ];
    }

    public function test_fromArray_with_two_items_populates_all_fields(): void
    {
        $list = IntentList::fromArray([
            'items' => [
                self::intentRow('a1', 'sess_1'),
                self::intentRow('a2', 'sess_2'),
            ],
            'page' => 1,
            'requestedPageSize' => 10,
            'currentPageSize' => 2,
            'totalPagesCount' => 1,
            'totalItemsCount' => 2,
        ]);

        self::assertCount(2, $list->items);
        self::assertSame('a1', $list->items[0]->shortId);
        self::assertSame('a2', $list->items[1]->shortId);
        self::assertSame(1, $list->page);
        self::assertSame(10, $list->requestedPageSize);
        self::assertSame(2, $list->currentPageSize);
        self::assertSame(1, $list->totalPagesCount);
        self::assertSame(2, $list->totalItemsCount);
    }

    public function test_fromArray_empty_items_is_valid_empty_page(): void
    {
        $list = IntentList::fromArray([
            'items' => [],
            'page' => 999,
            'requestedPageSize' => 10,
            'currentPageSize' => 0,
            'totalPagesCount' => 1,
            'totalItemsCount' => 5,
        ]);
        self::assertSame([], $list->items);
        self::assertSame(999, $list->page);
        self::assertSame(0, $list->currentPageSize);
        self::assertSame(5, $list->totalItemsCount);
    }

    public function test_fromArray_missing_items_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentList: missing or non-array items');
        IntentList::fromArray([
            'page' => 1,
            'requestedPageSize' => 10,
            'currentPageSize' => 0,
            'totalPagesCount' => 0,
            'totalItemsCount' => 0,
        ]);
    }

    public function test_fromArray_non_int_page_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentList: missing or non-integer page');
        IntentList::fromArray([
            'items' => [],
            'page' => '1',
            'requestedPageSize' => 10,
            'currentPageSize' => 0,
            'totalPagesCount' => 0,
            'totalItemsCount' => 0,
        ]);
    }

    public function test_fromArray_malformed_item_propagates(): void
    {
        $bad = self::intentRow('a1', 'sess_1');
        unset($bad['shortId']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails: missing or non-string shortId');
        IntentList::fromArray([
            'items' => [$bad],
            'page' => 1,
            'requestedPageSize' => 10,
            'currentPageSize' => 1,
            'totalPagesCount' => 1,
            'totalItemsCount' => 1,
        ]);
    }
}
