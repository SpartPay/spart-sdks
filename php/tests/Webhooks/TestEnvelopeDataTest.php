<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\TestEnvelopeData;

/**
 * Locks down the wire shape of the `test` sub-envelope used by
 * webhook.test events ("send a test webhook" merchant action).
 *
 * Server JSON shape: { merchantAppName, sentAt }
 *
 * The earlier SDK draft assumed a `nonce` field — that does not exist
 * server-side. Test-event uniqueness is provided by the outer envelope
 * id + the deliveryId surfaced via Event::$deliveryId.
 */
final class TestEnvelopeDataTest extends TestCase
{
    public function test_happy_path(): void
    {
        $d = TestEnvelopeData::fromArray([
            'merchantAppName' => 'Acme Store',
            'sentAt'          => '2026-05-06T12:00:00+00:00',
        ]);
        self::assertSame('Acme Store', $d->merchantAppName);
        self::assertSame('2026-05-06T12:00:00+00:00', $d->sentAt);
    }

    public function test_throws_on_missing_merchantAppName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestEnvelopeData::fromArray(['sentAt' => '2026-05-06T12:00:00+00:00']);
    }

    public function test_throws_on_missing_sentAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestEnvelopeData::fromArray(['merchantAppName' => 'Acme Store']);
    }

    public function test_throws_on_non_string_merchantAppName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestEnvelopeData::fromArray([
            'merchantAppName' => 42,
            'sentAt'          => '2026-05-06T12:00:00+00:00',
        ]);
    }
}
