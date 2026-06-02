<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Internal\ResponseMapper;

/**
 * Tests for {@see ResponseMapper::toIntentDetails} — the read-side
 * counterpart to {@see ResponseMapper::toIntentResult}. Failure
 * mapping is shared via `throwForFailureStatus`, so we verify only
 * the read-specific success path plus a representative sample of
 * failure codes (401, 404 generic, 500) to confirm the same surface
 * is reachable through the read endpoint.
 */
final class ResponseMapperReadTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function intentValue(): array
    {
        return [
            'shortId' => 'abc123',
            'total' => ['value' => '100.00', 'currency' => 'CAD'],
            'lineItems' => [['name' => 'Widget', 'quantity' => 1]],
            'createdAt' => '2026-05-11T12:34:56+00:00',
            'orderId' => null,
            'sessionId' => 'sess_xyz',
            'isCompleted' => false,
            'links' => [['name' => 'Checkout', 'url' => 'https://example.test/c/abc123']],
        ];
    }

    public function test_200_unwraps_result_envelope_and_returns_intent_details(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => self::intentValue(),
            'error' => null,
        ]);
        $details = ResponseMapper::toIntentDetails(
            new HttpResponse(200, ['content-type' => 'application/json'], $body),
        );
        self::assertSame('abc123', $details->shortId);
        self::assertSame('CAD', $details->total->currency);
        self::assertCount(1, $details->lineItems);
    }

    public function test_200_with_missing_value_throws_spart_api_exception(): void
    {
        $body = (string) json_encode(['isSuccessful' => true]);
        try {
            ResponseMapper::toIntentDetails(new HttpResponse(200, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertStringContainsString('missing "value"', $e->getMessage());
        }
    }

    public function test_200_with_malformed_value_throws_spart_api_exception_with_underlying_cause(): void
    {
        $bad = self::intentValue();
        unset($bad['shortId']);
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => $bad,
        ]);
        try {
            ResponseMapper::toIntentDetails(new HttpResponse(200, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertStringContainsString('malformed IntentDetails body', $e->getMessage());
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function test_200_with_non_json_body_throws_transport_exception(): void
    {
        $this->expectException(SpartTransportException::class);
        ResponseMapper::toIntentDetails(new HttpResponse(200, [], '<html>oops</html>'));
    }

    public function test_401_throws_auth_exception(): void
    {
        $this->expectException(SpartAuthException::class);
        ResponseMapper::toIntentDetails(new HttpResponse(401, [], ''));
    }

    public function test_404_throws_generic_spart_api_exception_with_status_404(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'value' => null,
            'error' => ['code' => 'intent.not_found', 'message' => "invalid id 'abc123'"],
        ]);
        try {
            ResponseMapper::toIntentDetails(new HttpResponse(404, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame("invalid id 'abc123'", $e->getMessage());
            self::assertSame('intent.not_found', $e->errorCode);
        }
    }

    public function test_500_throws_server_exception(): void
    {
        $this->expectException(SpartServerException::class);
        ResponseMapper::toIntentDetails(new HttpResponse(500, [], ''));
    }
}
