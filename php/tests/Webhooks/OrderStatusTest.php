<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\OrderStatus;

/**
 * Locks down the wire-encoded order status set so consumers can branch
 * on named constants instead of magic strings, and so that any silent
 * server-side drift surfaces here as a test failure instead of in a
 * merchant's checkout funnel.
 *
 * The unusual "allpaymentsauthorized" wire string (no separator) is
 * NOT a typo — it is the literal lowercased PascalCase enum name.
 */
final class OrderStatusTest extends TestCase
{
    public function test_canonical_constants_match_server(): void
    {
        self::assertSame('placed', OrderStatus::PLACED);
        self::assertSame('allpaymentsauthorized', OrderStatus::ALL_PAYMENTS_AUTHORIZED);
        self::assertSame('completed', OrderStatus::COMPLETED);
        self::assertSame('canceled', OrderStatus::CANCELED);
        self::assertSame('expired', OrderStatus::EXPIRED);
    }

    public function test_all_lists_exactly_five_statuses(): void
    {
        self::assertCount(5, OrderStatus::ALL);
        self::assertSame(
            ['placed', 'allpaymentsauthorized', 'completed', 'canceled', 'expired'],
            OrderStatus::ALL,
        );
    }

    public function test_canonical_status_returns_true_for_known(): void
    {
        self::assertTrue(OrderStatus::isKnown('completed'));
        self::assertTrue(OrderStatus::isKnown('allpaymentsauthorized'));
    }

    public function test_canonical_status_returns_false_for_unknown(): void
    {
        self::assertFalse(OrderStatus::isKnown('cancelled')); // double-L: wrong
        self::assertFalse(OrderStatus::isKnown('Completed'));  // capitalised: wrong
        self::assertFalse(OrderStatus::isKnown(''));
    }
}
