<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Internal\ResponseMapper;

/**
 * Tests for {@see ResponseMapper::toEligibility} — read-side mapping for
 * `GET /api/merchants/eligibility`. Failure mapping is shared with
 * {@see ResponseMapper::toIntentResult} via `throwForFailureStatus`, so
 * we verify the eligibility-specific success path plus a representative
 * sample of failure codes (401, 500) to confirm the same surface is
 * reachable through this endpoint.
 */
final class ResponseMapperEligibilityTest extends TestCase
{
    public function test_200_unwraps_result_envelope_and_returns_eligibility(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['eligible' => true, 'reasons' => []],
            'error' => null,
        ]);
        $eligibility = ResponseMapper::toEligibility(
            new HttpResponse(200, ['content-type' => 'application/json'], $body),
        );
        self::assertTrue($eligibility->eligible);
        self::assertSame([], $eligibility->reasons);
    }

    public function test_200_with_eligible_false_and_reasons_returns_eligibility_with_reasons(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => [
                'eligible' => false,
                'reasons' => [
                    [
                        'code' => 'merchant.not_connected_to_stripe',
                        'message' => 'Merchant has not connected to Stripe.',
                        'additionalData' => null,
                    ],
                ],
            ],
        ]);
        $eligibility = ResponseMapper::toEligibility(
            new HttpResponse(200, ['content-type' => 'application/json'], $body),
        );
        self::assertFalse($eligibility->eligible);
        self::assertCount(1, $eligibility->reasons);
        self::assertSame('merchant.not_connected_to_stripe', $eligibility->reasons[0]->code);
        self::assertSame('Merchant has not connected to Stripe.', $eligibility->reasons[0]->message);
    }

    public function test_200_with_missing_value_throws_spart_api_exception(): void
    {
        $body = (string) json_encode(['isSuccessful' => true]);
        try {
            ResponseMapper::toEligibility(new HttpResponse(200, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertStringContainsString('missing "value"', $e->getMessage());
        }
    }

    public function test_200_with_malformed_value_throws_spart_api_exception_with_underlying_cause(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['reasons' => []],
        ]);
        try {
            ResponseMapper::toEligibility(new HttpResponse(200, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertStringContainsString('malformed Eligibility body', $e->getMessage());
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function test_401_throws_auth_exception(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => ['code' => 'auth.unauthorized'],
        ]);
        $this->expectException(SpartAuthException::class);
        ResponseMapper::toEligibility(new HttpResponse(401, [], $body));
    }

    public function test_500_throws_server_exception(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => ['code' => 'server.internal'],
        ]);
        $this->expectException(SpartServerException::class);
        ResponseMapper::toEligibility(new HttpResponse(500, [], $body));
    }
}
