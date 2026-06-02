<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Dtos;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\Eligibility;

final class EligibilityTest extends TestCase
{
    public function test_fromArray_with_eligible_true_and_empty_reasons(): void
    {
        $eligibility = Eligibility::fromArray(['eligible' => true, 'reasons' => []]);
        self::assertTrue($eligibility->eligible);
        self::assertSame([], $eligibility->reasons);
    }

    public function test_fromArray_with_eligible_false_and_multiple_reasons(): void
    {
        $eligibility = Eligibility::fromArray([
            'eligible' => false,
            'reasons' => [
                [
                    'code' => 'merchant.not_connected_to_stripe',
                    'message' => 'Merchant has not connected to Stripe.',
                    'additionalData' => null,
                ],
                [
                    'code' => 'merchant.onboarding_incomplete',
                    'message' => 'Cannot create intent: merchant onboarding incomplete.',
                    'additionalData' => null,
                ],
            ],
        ]);

        self::assertFalse($eligibility->eligible);
        self::assertCount(2, $eligibility->reasons);
        self::assertSame('merchant.not_connected_to_stripe', $eligibility->reasons[0]->code);
        self::assertSame('Merchant has not connected to Stripe.', $eligibility->reasons[0]->message);
        self::assertNull($eligibility->reasons[0]->additionalData);
        self::assertSame('merchant.onboarding_incomplete', $eligibility->reasons[1]->code);
    }

    public function test_fromArray_throws_when_eligible_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Eligibility: missing or non-bool eligible');
        Eligibility::fromArray(['reasons' => []]);
    }

    public function test_fromArray_throws_when_eligible_is_string_true(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Eligibility: missing or non-bool eligible');
        Eligibility::fromArray(['eligible' => 'true', 'reasons' => []]);
    }

    public function test_fromArray_throws_when_reasons_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Eligibility: missing or non-array reasons');
        Eligibility::fromArray(['eligible' => true]);
    }

    public function test_fromArray_throws_when_reasons_contains_non_object(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Eligibility: reasons[0] is not an object');
        Eligibility::fromArray(['eligible' => false, 'reasons' => ['not-an-object']]);
    }

    public function test_fromArray_throws_when_reason_element_malformed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EligibilityReason: missing or non-string message');
        Eligibility::fromArray(['eligible' => false, 'reasons' => [['code' => 'x']]]);
    }
}
