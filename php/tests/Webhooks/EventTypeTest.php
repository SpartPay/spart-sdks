<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\EventType;

/**
 * Locks the SDK's `EventType` enum to the canonical 6-value set the Spart
 * server emits.
 *
 * The set is intentionally closed: any new server-side event type must
 * be a deliberate change here AND on the server, never one or the other.
 *
 * The "absent cases" test catches three categories of drift:
 *  - wrong spellings the SDK previously had (`order.cancelled` w/ two Ls)
 *  - speculative cases the SDK invented but the server never emits
 *    (`payment.captured`, `payment.failed`, `intent.expired`, `test.ping`)
 *  - any future case added to the enum without a matching server change
 */
final class EventTypeTest extends TestCase
{
    public function test_enum_has_exactly_six_canonical_cases(): void
    {
        self::assertCount(6, EventType::cases());
    }

    public function test_each_canonical_value_maps_to_its_case(): void
    {
        self::assertSame(EventType::IntentCreated, EventType::from('intent.created'));
        self::assertSame(EventType::PaymentAuthorized, EventType::from('payment.authorized'));
        self::assertSame(EventType::OrderCompleted, EventType::from('order.completed'));
        self::assertSame(EventType::OrderCanceled, EventType::from('order.canceled'));
        self::assertSame(EventType::OrderExpired, EventType::from('order.expired'));
        self::assertSame(EventType::WebhookTest, EventType::from('webhook.test'));
    }

    /**
     * @dataProvider nonCanonicalValues
     */
    public function test_non_canonical_values_do_not_resolve(string $wireValue): void
    {
        self::assertNull(EventType::tryFrom($wireValue));
    }

    /** @return iterable<string, array{0: string}> */
    public static function nonCanonicalValues(): iterable
    {
        // Old SDK had this with two Ls — server uses `order.canceled`.
        yield 'double-L cancelled (British spelling)' => ['order.cancelled'];

        // Old SDK invented these; server never emits them.
        yield 'speculative test.ping' => ['test.ping'];
        yield 'speculative intent.expired' => ['intent.expired'];
        yield 'speculative payment.captured' => ['payment.captured'];
        yield 'speculative payment.failed' => ['payment.failed'];

        // Sanity: completely unknown types resolve to null.
        yield 'unknown future event' => ['something.new'];
    }
}
