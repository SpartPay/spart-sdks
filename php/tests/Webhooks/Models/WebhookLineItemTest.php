<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookLineItem;

/**
 * Locks down the wire shape of {name: string, quantity: int}.
 *
 * Note: the WEBHOOK-side LineItem is intentionally narrower than the
 * REQUEST-side Spart\Sdk\Models\LineItem (which carries description,
 * unit price, image URI, etc.). The webhook view only carries name +
 * quantity because that's all merchants need post-fact.
 */
final class WebhookLineItemTest extends TestCase
{
    public function test_happy_path(): void
    {
        $li = WebhookLineItem::fromArray(['name' => 'Widget', 'quantity' => 2]);
        self::assertSame('Widget', $li->name);
        self::assertSame(2, $li->quantity);
    }

    public function test_throws_on_missing_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookLineItem::fromArray(['quantity' => 1]);
    }

    public function test_throws_on_missing_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookLineItem::fromArray(['name' => 'Widget']);
    }

    public function test_throws_on_non_int_quantity(): void
    {
        // C# server emits int; reject non-int (e.g. "2", 2.5) to surface drift early.
        $this->expectException(\InvalidArgumentException::class);
        WebhookLineItem::fromArray(['name' => 'Widget', 'quantity' => '2']);
    }

    public function test_throws_on_float_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookLineItem::fromArray(['name' => 'Widget', 'quantity' => 2.5]);
    }

    public function test_throws_on_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookLineItem::fromArray(['name' => '', 'quantity' => 1]);
    }
}
