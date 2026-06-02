<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Dtos;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\CreateIntentRequest;
use Spart\Sdk\Internal\JsonEncoder;
use Spart\Sdk\Models\Contact;
use Spart\Sdk\Models\LineItem;
use Spart\Sdk\Models\Money;
use Spart\Sdk\Models\OrderOptions;

final class CreateIntentRequestTest extends TestCase
{
    public function test_to_array_emits_canonical_field_names(): void
    {
        $req = new CreateIntentRequest(
            total: Money::fromString('10.00', 'EUR'),
            lineItems: [new LineItem('Socks', 2, description: 'Wool')],
            sparter: new Contact(email: 'a@b.com', firstName: 'A', lastName: 'B'),
            sessionId: 'wc_order_42',
            options: new OrderOptions(
                maxDuration: new \DateInterval('P1D'),
                returnUri: 'https://shop.example/return',
                cancelUri: 'https://shop.example/cancel',
            ),
        );

        $arr = $req->toArray();
        self::assertSame('wc_order_42', $arr['sessionId']);
        self::assertArrayHasKey('total', $arr);
        self::assertArrayHasKey('lineItems', $arr);
        self::assertArrayHasKey('sparter', $arr);
        self::assertArrayNotHasKey('contact', $arr);
        self::assertArrayNotHasKey('currency', $arr);
        self::assertArrayNotHasKey('returnUri', $arr);
        self::assertArrayNotHasKey('cancelUri', $arr);
        self::assertSame('a@b.com', $arr['sparter']['email']);
        self::assertSame('Wool', $arr['lineItems'][0]['description']);
        self::assertSame(864_000_000_000, $arr['options']['maxDurationTicks']);
        self::assertSame('https://shop.example/return', $arr['options']['returnUri']);
        self::assertSame('https://shop.example/cancel', $arr['options']['cancelUri']);
    }

    public function test_session_id_is_omitted_when_null(): void
    {
        $req = new CreateIntentRequest(
            total: Money::fromString('5.00', 'EUR'),
            lineItems: [new LineItem('I', 1)],
            sparter: new Contact('a@b.com'),
        );
        self::assertArrayNotHasKey('sessionId', $req->toArray());
    }

    public function test_options_omits_optional_uris_when_null(): void
    {
        $req = new CreateIntentRequest(
            total: Money::fromString('5.00', 'EUR'),
            lineItems: [new LineItem('I', 1)],
            sparter: new Contact('a@b.com'),
            options: new OrderOptions(maxDuration: new \DateInterval('P1D')),
        );
        $arr = $req->toArray();
        self::assertSame(['maxDurationTicks' => 864_000_000_000], $arr['options']);
    }

    public function test_options_always_emits_max_duration_ticks_when_options_present(): void
    {
        // Server's OrderOptions validator runs unconditionally on the sub-object
        // and rejects MaxDurationTicks < MinDuration. Sending only URIs would 400.
        $req = new CreateIntentRequest(
            total: Money::fromString('5.00', 'EUR'),
            lineItems: [new LineItem('I', 1)],
            sparter: new Contact('a@b.com'),
            options: new OrderOptions(
                maxDuration: new \DateInterval('PT6H'),
                returnUri: 'https://x/r',
            ),
        );
        $arr = $req->toArray();
        self::assertArrayHasKey('maxDurationTicks', $arr['options']);
        self::assertSame(216_000_000_000, $arr['options']['maxDurationTicks']);
    }

    public function test_to_array_preserves_money_via_encoder(): void
    {
        $req = new CreateIntentRequest(
            total: Money::fromString('5.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
        );
        $json = JsonEncoder::encode($req->toArray());
        // total is now {value: 5.00, currency: "EUR"}.
        self::assertStringContainsString('"value":5.00', $json);
        self::assertStringContainsString('"currency":"EUR"', $json);
        self::assertStringNotContainsString('"value":"5.00"', $json);
    }

    public function test_to_array_does_not_include_unit_price_in_line_items(): void
    {
        $req = new CreateIntentRequest(
            total: Money::fromString('5.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
        );
        $arr = $req->toArray();
        self::assertSame(['name' => 'Item', 'quantity' => 1], $arr['lineItems'][0]);
        self::assertArrayNotHasKey('unitPrice', $arr['lineItems'][0]);
    }

    public function test_constructor_rejects_blank_session_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateIntentRequest(
            total: Money::fromString('10.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
            sessionId: '   ',
        );
    }

    public function test_constructor_rejects_session_id_over_64_chars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateIntentRequest(
            total: Money::fromString('10.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
            sessionId: str_repeat('a', 65),
        );
    }

    public function test_constructor_accepts_session_id_at_max_length(): void
    {
        $req = new CreateIntentRequest(
            total: Money::fromString('10.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
            sessionId: str_repeat('a', 64),
        );
        self::assertSame(str_repeat('a', 64), $req->sessionId);
    }

    public function test_constructor_rejects_empty_line_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateIntentRequest(
            total: Money::fromString('10.00', 'EUR'),
            lineItems: [],
            sparter: new Contact('a@b.com'),
        );
    }

    public function test_constructor_rejects_zero_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/total\.value must be > 0/');
        new CreateIntentRequest(
            total: Money::fromString('0', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
        );
    }

    public function test_constructor_rejects_zero_decimal_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateIntentRequest(
            total: Money::fromString('0.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
        );
    }

    public function test_constructor_rejects_negative_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateIntentRequest(
            total: Money::fromString('-5.00', 'EUR'),
            lineItems: [new LineItem('Item', 1)],
            sparter: new Contact('a@b.com'),
        );
    }
}
