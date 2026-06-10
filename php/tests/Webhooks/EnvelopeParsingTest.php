<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartValidationException;
use Spart\Sdk\Webhooks\Event;
use Spart\Sdk\Webhooks\EventType;
use Spart\Sdk\Webhooks\IntentEnvelopeData;
use Spart\Sdk\Webhooks\OrderEnvelopeData;
use Spart\Sdk\Webhooks\SignatureVerifier;

final class EnvelopeParsingTest extends TestCase
{
    private const SECRET = 'whsec_test_xyz';

    public function test_verify_and_parse_returns_event_for_valid_signed_envelope(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_1', 'type' => 'intent.created', 'createdAt' => '2026-05-06T10:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['intent' => [
                'shortId'     => 'i_short',
                'total'       => ['currency' => 'EUR', 'amount' => 25.00],
                'lineItems'   => [['name' => 'Widget', 'quantity' => 2]],
                'sparter'     => ['fullName' => 'Alice', 'email' => 'a@b.c'],
                'sessionId'   => 'wc_42',
                'countryCode' => 'IT',
                'createdAt'   => '2026-05-06T10:00:00+00:00',
                'expiresOn'   => '2026-05-13T10:00:00+00:00',
            ]],
        ]);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);
        $header = "t={$t},v1={$sig}";

        $evt = (new SignatureVerifier(self::SECRET))->verifyAndParse($body, $header, deliveryId: 'd1', attempt: 1);
        self::assertInstanceOf(Event::class, $evt);
        self::assertInstanceOf(IntentEnvelopeData::class, $evt->data);
        self::assertSame('d1', $evt->deliveryId);
        self::assertSame(1, $evt->attempt);
    }

    public function test_verify_and_parse_throws_on_invalid_signature(): void
    {
        $this->expectException(SpartValidationException::class);
        (new SignatureVerifier(self::SECRET))->verifyAndParse(
            rawBody: '{"id":"evt_1"}',
            headerValue: 't=' . time() . ',v1=deadbeef',
            deliveryId: 'd',
            attempt: 1,
        );
    }

    public function test_verify_and_parse_accepts_unknown_event_type(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_2', 'type' => 'future.unknown', 'createdAt' => '2026-05-06T10:00:00Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => new \stdClass(),
        ]);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);
        $header = "t={$t},v1={$sig}";

        $evt = (new SignatureVerifier(self::SECRET))->verifyAndParse($body, $header, deliveryId: 'd', attempt: 1);
        self::assertSame('future.unknown', $evt->type);
        self::assertNull($evt->knownType);
    }

    public function test_verify_and_parse_throws_validation_exception_when_body_is_malformed_but_signature_valid(): void
    {
        // Valid JSON but missing all required outer envelope fields
        $body = '{"id":"evt_1"}';
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);
        $header = "t={$t},v1={$sig}";

        try {
            (new SignatureVerifier(self::SECRET))->verifyAndParse(
                rawBody: $body,
                headerValue: $header,
                deliveryId: 'd',
                attempt: 1,
            );
            $this->fail('Expected SpartValidationException');
        } catch (SpartValidationException $e) {
            // Confirm the underlying \InvalidArgumentException from Event::fromJson is preserved
            $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            $this->assertStringContainsString('envelope is invalid', $e->getMessage());
        }
    }

    public function test_verify_and_parse_validation_exception_has_null_status_for_local_signature_failure(): void
    {
        // Local validation (signature mismatch) must not pretend to carry an
        // HTTP 400 status — there was no HTTP call. Consumers branching on
        // $e->statusCode === 400 would be misled into thinking the Spart API
        // had returned a 400.
        try {
            (new SignatureVerifier(self::SECRET))->verifyAndParse(
                rawBody: '{"id":"evt_1"}',
                headerValue: 't=' . time() . ',v1=deadbeef',
                deliveryId: 'd',
                attempt: 1,
            );
            $this->fail('Expected SpartValidationException');
        } catch (SpartValidationException $e) {
            $this->assertNull($e->statusCode);
        }
    }

    public function test_verify_and_parse_throws_validation_exception_when_json_is_malformed_but_signature_valid(): void
    {
        $body = 'not json at all';
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);
        $header = "t={$t},v1={$sig}";

        $this->expectException(SpartValidationException::class);
        (new SignatureVerifier(self::SECRET))->verifyAndParse(
            rawBody: $body,
            headerValue: $header,
            deliveryId: 'd',
            attempt: 1,
        );
    }

    public function test_verify_and_parse_routes_order_created_to_order_envelope_with_payment_parts(): void
    {
        $body = (string) json_encode([
            'id' => 'evt_oc', 'type' => 'order.created', 'createdAt' => '2026-06-09T00:15:22Z',
            'apiVersion' => 'v1', 'merchantAppId' => 'app_1',
            'data' => ['order' => [
                'shortId'       => 'o_short',
                'originalTotal' => ['currency' => 'EUR', 'amount' => 360.00],
                'finalTotal'    => ['currency' => 'EUR', 'amount' => 361.00],
                'lineItems'     => [['name' => 'Bands', 'quantity' => 2]],
                'sparter'       => ['fullName' => 'Beppe B', 'email' => 'o****p@g****l.com'],
                'paymentParts'  => [[
                    'id'          => '11111111-1111-1111-1111-111111111111',
                    'amount'      => 100,
                    'amountType'  => 'Percent',
                    'status'      => 'captured',
                    'isSparter'   => true,
                    'payee'       => ['fullName' => 'Beppe B', 'email' => 'o****p@g****l.com'],
                    'payeeCharge' => [
                        'net'   => ['currency' => 'EUR', 'amount' => 355.01],
                        'total' => ['currency' => 'EUR', 'amount' => 360.00],
                        'fees'  => ['platform' => 4.99],
                    ],
                    'authorizedAt' => '2026-06-09T00:15:16+00:00',
                    'capturedAt'   => '2026-06-09T00:16:00+00:00',
                    'releasedAt'   => null,
                ]],
                'sessionId'   => 'spart-wc-2c20ebf2-63',
                'status'      => 'placed',
                'countryCode' => 'IT',
                'createdAt'   => '2026-06-09T00:15:16+00:00',
            ]],
        ]);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);
        $header = "t={$t},v1={$sig}";

        $evt = (new SignatureVerifier(self::SECRET))->verifyAndParse($body, $header, deliveryId: 'd', attempt: 1);

        self::assertSame(EventType::OrderCreated, $evt->knownType);
        self::assertInstanceOf(OrderEnvelopeData::class, $evt->data);
        self::assertCount(1, $evt->data->paymentParts);
        self::assertSame('Percent', $evt->data->paymentParts[0]->amountType);
        self::assertSame('Beppe B', $evt->data->paymentParts[0]->payee->fullName);
        self::assertSame(360.0, $evt->data->paymentParts[0]->payeeCharge->total->amount);
    }
}
