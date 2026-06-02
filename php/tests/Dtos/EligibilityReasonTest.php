<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Dtos;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\EligibilityReason;

final class EligibilityReasonTest extends TestCase
{
    public function test_fromArray_with_full_body_populates_all_fields(): void
    {
        $extra = ['k' => 'v'];
        $reason = EligibilityReason::fromArray([
            'code' => 'merchant.not_connected_to_stripe',
            'message' => 'Merchant has not connected to Stripe.',
            'additionalData' => $extra,
        ]);

        self::assertSame('merchant.not_connected_to_stripe', $reason->code);
        self::assertSame('Merchant has not connected to Stripe.', $reason->message);
        self::assertSame($extra, $reason->additionalData);
    }

    public function test_fromArray_treats_missing_additionalData_as_null(): void
    {
        $reason = EligibilityReason::fromArray([
            'code' => 'x',
            'message' => 'y',
        ]);
        self::assertNull($reason->additionalData);
    }

    public function test_fromArray_treats_explicit_null_additionalData_as_null(): void
    {
        $reason = EligibilityReason::fromArray([
            'code' => 'x',
            'message' => 'y',
            'additionalData' => null,
        ]);
        self::assertNull($reason->additionalData);
    }

    public function test_fromArray_throws_when_code_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EligibilityReason: missing or non-string code');
        EligibilityReason::fromArray(['message' => 'y']);
    }

    public function test_fromArray_throws_when_code_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EligibilityReason: missing or non-string code');
        EligibilityReason::fromArray(['code' => '', 'message' => 'y']);
    }

    public function test_fromArray_throws_when_message_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EligibilityReason: missing or non-string message');
        EligibilityReason::fromArray(['code' => 'x']);
    }

    public function test_fromArray_throws_when_message_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EligibilityReason: missing or non-string message');
        EligibilityReason::fromArray(['code' => 'x', 'message' => '']);
    }
}
