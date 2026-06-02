<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Event;
use Spart\Sdk\Webhooks\EventType;
use Spart\Sdk\Webhooks\IntentEnvelopeData;
use Spart\Sdk\Webhooks\OrderEnvelopeData;
use Spart\Sdk\Webhooks\PaymentEnvelopeData;
use Spart\Sdk\Webhooks\TestEnvelopeData;

final class EventTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function intentSubEnvelope(): array
    {
        return [
            'shortId'     => 'i_short',
            'total'       => ['currency' => 'EUR', 'amount' => 25.00],
            'lineItems'   => [['name' => 'Widget', 'quantity' => 2]],
            'sparter'     => ['fullName' => 'Alice', 'email' => 'a@b.c'],
            'sessionId'   => 'wc_42',
            'countryCode' => 'IT',
            'createdAt'   => '2026-05-06T10:00:00+00:00',
            'expiresOn'   => '2026-05-13T10:00:00+00:00',
        ];
    }

    /** @return array<string,mixed> */
    private static function orderSubEnvelope(string $status = 'completed'): array
    {
        return [
            'shortId'       => 'o_short',
            'originalTotal' => ['currency' => 'EUR', 'amount' => 30.00],
            'finalTotal'    => ['currency' => 'EUR', 'amount' => 25.00],
            'lineItems'     => [['name' => 'Widget', 'quantity' => 2]],
            'sparter'       => ['fullName' => 'Alice', 'email' => 'a@b.c'],
            'sessionId'     => 'wc_42',
            'status'        => $status,
            'countryCode'   => 'IT',
            'createdAt'     => '2026-05-06T11:00:00+00:00',
        ];
    }

    /** @return array<string,mixed> */
    private static function paymentSubEnvelope(): array
    {
        return [
            'orderShortId'     => 'o_short',
            'sessionId'        => 'wc_42',
            'paymentPartId'    => '550e8400-e29b-41d4-a716-446655440000',
            'amountAuthorized' => ['currency' => 'EUR', 'amount' => 8.33],
            'payee'            => ['fullName' => 'Bob', 'email' => 'b@b.c'],
            'authorizedAt'     => '2026-05-06T11:30:00+00:00',
        ];
    }

    /** @return array<string,mixed> */
    private static function testSubEnvelope(): array
    {
        return ['merchantAppName' => 'Acme', 'sentAt' => '2026-05-06T12:00:00+00:00'];
    }

    public function test_intent_created_parses(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_1', 'type' => 'intent.created', 'createdAt' => '2026-05-06T10:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['intent' => self::intentSubEnvelope()],
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd1', attempt: 1);
        self::assertSame('intent.created', $evt->type);
        self::assertSame(EventType::IntentCreated, $evt->knownType);
        self::assertInstanceOf(IntentEnvelopeData::class, $evt->data);
        self::assertSame('wc_42', $evt->data->sessionId);
        self::assertSame('i_short', $evt->data->shortId);
        self::assertSame('d1', $evt->deliveryId);
        self::assertSame(1, $evt->attempt);
        self::assertSame('app_1', $evt->merchantAppId);
    }

    public function test_payment_authorized_parses(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_2', 'type' => 'payment.authorized', 'createdAt' => '2026-05-06T10:01:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['payment' => self::paymentSubEnvelope()],
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertInstanceOf(PaymentEnvelopeData::class, $evt->data);
        self::assertSame(8.33, $evt->data->amountAuthorized->amount);
        self::assertSame('o_short', $evt->data->orderShortId);
    }

    public function test_order_completed_parses(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_3', 'type' => 'order.completed', 'createdAt' => '2026-05-06T11:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['order' => self::orderSubEnvelope('completed')],
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertInstanceOf(OrderEnvelopeData::class, $evt->data);
        self::assertSame('o_short', $evt->data->shortId);
        self::assertSame('completed', $evt->data->status);
    }

    public function test_order_canceled_parses(): void
    {
        // Single-L spelling per the server's canonical event-type set —
        // see EventTypeTest for the full canonical-vs-deprecated set.
        $body = (string) json_encode([
            'id' => 'evt_3b', 'type' => 'order.canceled', 'createdAt' => '2026-05-06T11:05:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['order' => self::orderSubEnvelope('canceled')],
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertSame(EventType::OrderCanceled, $evt->knownType);
        self::assertInstanceOf(OrderEnvelopeData::class, $evt->data);
        self::assertSame('canceled', $evt->data->status);
    }

    public function test_order_expired_parses(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_3c', 'type' => 'order.expired', 'createdAt' => '2026-05-06T11:10:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['order' => self::orderSubEnvelope('expired')],
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertSame(EventType::OrderExpired, $evt->knownType);
        self::assertInstanceOf(OrderEnvelopeData::class, $evt->data);
        self::assertSame('expired', $evt->data->status);
    }

    public function test_webhook_test_parses(): void
    {
        // Server emits `webhook.test` (not the SDK's old `test.ping`). The
        // sub-envelope body shape is asserted by TestEnvelopeDataTest.
        $body = (string) json_encode([
            'id' => 'evt_4', 'type' => 'webhook.test', 'createdAt' => '2026-05-06T12:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['test' => self::testSubEnvelope()],
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertSame(EventType::WebhookTest, $evt->knownType);
        self::assertInstanceOf(TestEnvelopeData::class, $evt->data);
        self::assertSame('Acme', $evt->data->merchantAppName);
    }

    public function test_unknown_type_keeps_raw_string_and_null_known(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_5', 'type' => 'future.unknown', 'createdAt' => '2026-05-06T12:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => new \stdClass(),
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertSame('future.unknown', $evt->type);
        self::assertNull($evt->knownType);
        self::assertNull($evt->data);
    }

    public function test_throws_on_missing_required_outer_envelope_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Event::fromJson('{"type":"intent.created"}', deliveryId: 'd', attempt: 1);
    }

    public function test_throws_when_outer_envelope_field_is_empty_string(): void
    {
        $body = (string) json_encode([
            'id' => '', // empty — should be rejected
            'type' => 'intent.created',
            'createdAt' => '2026-05-06T10:00:00Z',
            'apiVersion' => 'v1',
            'merchantAppId' => 'app_1',
            'data' => ['intent' => self::intentSubEnvelope()],
        ]);
        $this->expectException(\InvalidArgumentException::class);
        Event::fromJson($body, deliveryId: 'd', attempt: 1);
    }

    public function test_throws_when_outer_envelope_type_is_empty_string(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_1',
            'type' => '', // empty — should be rejected
            'createdAt' => '2026-05-06T10:00:00Z',
            'apiVersion' => 'v1',
            'merchantAppId' => 'app_1',
        ]);
        $this->expectException(\InvalidArgumentException::class);
        Event::fromJson($body, deliveryId: 'd', attempt: 1);
    }

    public function test_known_type_with_miskeyed_data_yields_null_data(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_x', 'type' => 'intent.created', 'createdAt' => '2026-05-06T12:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['order' => self::orderSubEnvelope()], // wrong sub-key
        ]);
        $evt = Event::fromJson($body, deliveryId: 'd', attempt: 1);
        self::assertSame(EventType::IntentCreated, $evt->knownType);
        self::assertNull($evt->data); // intentional forward-compat: unknown/miskeyed sub-envelope shapes yield null data
    }
}
