<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Dtos;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\IntentDetails;

final class IntentDetailsTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validBody(): array
    {
        return [
            'shortId' => 'abc123',
            'total' => ['value' => '100.00', 'currency' => 'CAD'],
            'lineItems' => [
                ['name' => 'Widget', 'quantity' => 2, 'description' => 'A test widget', 'imageUri' => null],
                ['name' => 'Gadget', 'quantity' => 1],
            ],
            'createdAt' => '2026-05-11T12:34:56+00:00',
            'orderId' => null,
            'sessionId' => 'sess_xyz',
            'isCompleted' => false,
            'links' => [
                ['name' => 'Checkout', 'url' => 'https://example.test/c/abc123'],
            ],
        ];
    }

    public function test_fromArray_with_full_body_populates_all_fields(): void
    {
        $details = IntentDetails::fromArray(self::validBody());

        self::assertSame('abc123', $details->shortId);
        self::assertSame('100.00', $details->total->value);
        self::assertSame('CAD', $details->total->currency);
        self::assertCount(2, $details->lineItems);
        self::assertSame('Widget', $details->lineItems[0]->name);
        self::assertSame(2, $details->lineItems[0]->quantity);
        self::assertSame('A test widget', $details->lineItems[0]->description);
        self::assertNull($details->lineItems[0]->imageUri);
        self::assertSame('Gadget', $details->lineItems[1]->name);
        self::assertNull($details->lineItems[1]->description);
        self::assertSame('2026-05-11T12:34:56+00:00', $details->createdAt->format('Y-m-d\TH:i:sP'));
        self::assertNull($details->orderId);
        self::assertSame('sess_xyz', $details->sessionId);
        self::assertFalse($details->isCompleted);
        self::assertCount(1, $details->links);
        self::assertSame('Checkout', $details->links[0]->name);
        self::assertSame('https://example.test/c/abc123', $details->links[0]->url);
    }

    public function test_fromArray_accepts_numeric_total_value_from_real_wire(): void
    {
        // Server emits MoneyDto.value as a C# `decimal` which lands in PHP
        // as int|float after json_decode — NOT as a string. Real-wire
        // regression pin (#208 T18 follow-up).
        $body = self::validBody();
        $body['total'] = ['value' => 100.0, 'currency' => 'CAD'];
        $details = IntentDetails::fromArray($body);
        self::assertSame('100', $details->total->value);
    }

    public function test_fromArray_accepts_integer_total_value(): void
    {
        $body = self::validBody();
        $body['total'] = ['value' => 100, 'currency' => 'CAD'];
        $details = IntentDetails::fromArray($body);
        self::assertSame('100', $details->total->value);
    }

    public function test_fromArray_accepts_fractional_float_total_value(): void
    {
        $body = self::validBody();
        $body['total'] = ['value' => 12.34, 'currency' => 'CAD'];
        $details = IntentDetails::fromArray($body);
        self::assertSame('12.34', $details->total->value);
    }

    public function test_fromArray_rejects_non_finite_total_value(): void
    {
        $body = self::validBody();
        $body['total'] = ['value' => INF, 'currency' => 'CAD'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails.total: value must be finite');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_rejects_array_total_value(): void
    {
        $body = self::validBody();
        $body['total'] = ['value' => ['oops'], 'currency' => 'CAD'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails.total: value must be a JSON number or string');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_completed_intent_with_orderId_and_OrderDetails_link(): void
    {
        $body = self::validBody();
        $body['orderId'] = 'ord_456';
        $body['isCompleted'] = true;
        $body['links'] = [['name' => 'OrderDetails', 'url' => 'https://example.test/o/ord_456']];

        $details = IntentDetails::fromArray($body);

        self::assertSame('ord_456', $details->orderId);
        self::assertTrue($details->isCompleted);
        self::assertSame('OrderDetails', $details->links[0]->name);
    }

    public function test_fromArray_missing_shortId_throws(): void
    {
        $body = self::validBody();
        unset($body['shortId']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails: missing or non-string shortId');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_missing_total_throws(): void
    {
        $body = self::validBody();
        unset($body['total']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails: missing or non-object total');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_missing_lineItems_throws(): void
    {
        $body = self::validBody();
        unset($body['lineItems']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails: missing or non-array lineItems');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_missing_links_throws(): void
    {
        $body = self::validBody();
        unset($body['links']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IntentDetails: missing or non-array links');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_invalid_createdAt_throws(): void
    {
        $body = self::validBody();
        $body['createdAt'] = 'not-a-date';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('createdAt is not a valid ISO-8601 timestamp');
        IntentDetails::fromArray($body);
    }

    public function test_fromArray_lineItem_with_non_int_quantity_throws(): void
    {
        $body = self::validBody();
        $body['lineItems'] = [['name' => 'X', 'quantity' => '2']];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quantity must be an integer');
        IntentDetails::fromArray($body);
    }
}
