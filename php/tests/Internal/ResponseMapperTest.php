<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Exceptions\SpartRateLimitException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Exceptions\SpartValidationException;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Internal\ResponseMapper;

final class ResponseMapperTest extends TestCase
{
    public function test_201_unwraps_result_envelope_and_returns_intent_result_not_replay(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['intentShortId' => 'abc123', 'checkoutUrl' => 'https://x/c'],
            'error' => null,
        ]);
        $r = ResponseMapper::toIntentResult(new HttpResponse(201, ['content-type' => 'application/json'], $body));
        self::assertSame('abc123', $r->intentShortId);
        self::assertSame('https://x/c', $r->checkoutUrl);
        self::assertFalse($r->wasIdempotentReplay);
    }

    public function test_200_unwraps_envelope_and_marks_replay(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['intentShortId' => 'abc123', 'checkoutUrl' => 'https://x/c'],
            'error' => null,
        ]);
        $r = ResponseMapper::toIntentResult(new HttpResponse(200, ['content-type' => 'application/json'], $body));
        self::assertTrue($r->wasIdempotentReplay);
    }

    public function test_201_with_missing_value_throws_spart_api_exception(): void
    {
        $body = (string) json_encode(['isSuccessful' => true]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(201, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertSame(201, $e->statusCode);
            self::assertStringContainsString('value', $e->getMessage());
        }
    }

    public function test_201_with_malformed_value_object_throws_spart_api_exception_preserving_cause(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['intentShortId' => '', 'checkoutUrl' => 'https://x/c'],
        ]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(201, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertSame(201, $e->statusCode);
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function test_200_with_non_json_body_throws_spart_transport_exception(): void
    {
        // Non-JSON in a 2xx response means the wire is broken (proxy
        // rewrote the body, gateway swallowed the response, etc.) — not
        // an API-level error. Mapping to SpartTransportException lets
        // RetryingHttpClient (Task 12) treat this as a transient failure
        // and retry the request.
        try {
            ResponseMapper::toIntentResult(new HttpResponse(200, [], '<html>oops</html>'));
            $this->fail('expected SpartTransportException');
        } catch (SpartTransportException $e) {
            self::assertStringContainsString('200', $e->getMessage());
        }
    }

    public function test_400_throws_validation_exception_with_error_message_code_and_details(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => [
                'code' => 'intent.session_conflict',
                'message' => 'session conflict',
                'additionalData' => [
                    ['code' => 'intent.session_conflict', 'message' => 'session id already used'],
                    ['code' => 'intent.session_conflict', 'message' => 'Try a different sessionId.'],
                ],
            ],
        ]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(400, ['content-type' => 'application/json'], $body));
            $this->fail('expected SpartValidationException');
        } catch (SpartValidationException $e) {
            self::assertSame('session conflict', $e->getMessage());
            self::assertSame(400, $e->statusCode);
            self::assertSame('intent.session_conflict', $e->errorCode);
            self::assertSame(['session id already used', 'Try a different sessionId.'], $e->errorDetails);
        }
    }

    public function test_400_with_string_additional_data_falls_back_gracefully(): void
    {
        // Server's `Result.Failure(code, message, string[] additionalData)` path (e.g. OrderFactory
        // ValidationFailed) emits AdditionalData as bare strings, not ErrorDetails objects.
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => [
                'code' => 'order.validation_failed',
                'message' => 'bad',
                'additionalData' => ['something-broke', 'another-thing'],
            ],
        ]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(400, [], $body));
            $this->fail('expected SpartValidationException');
        } catch (SpartValidationException $e) {
            self::assertSame('order.validation_failed', $e->errorCode);
            self::assertSame(['something-broke', 'another-thing'], $e->errorDetails);
        }
    }

    public function test_400_bodyless_uses_status_text_null_code_and_empty_details(): void
    {
        try {
            ResponseMapper::toIntentResult(new HttpResponse(400, [], ''));
            $this->fail('expected SpartValidationException');
        } catch (SpartValidationException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertNull($e->errorCode);
            self::assertSame([], $e->errorDetails);
            self::assertNotEmpty($e->getMessage());
        }
    }

    public function test_401_bodyless_throws_auth_exception(): void
    {
        try {
            ResponseMapper::toIntentResult(new HttpResponse(401, [], ''));
            $this->fail('expected SpartAuthException');
        } catch (SpartAuthException $e) {
            self::assertSame(401, $e->statusCode);
            self::assertNull($e->errorCode);
            self::assertSame([], $e->errorDetails);
        }
    }

    public function test_401_with_envelope_throws_auth_exception_with_message_and_code(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => ['code' => 'auth.unauthorized', 'message' => 'invalid api key'],
        ]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(401, [], $body));
            $this->fail('expected SpartAuthException');
        } catch (SpartAuthException $e) {
            self::assertSame('invalid api key', $e->getMessage());
            self::assertSame('auth.unauthorized', $e->errorCode);
        }
    }

    public function test_429_bodyless_throws_rate_limit_with_retry_after(): void
    {
        try {
            ResponseMapper::toIntentResult(new HttpResponse(429, ['retry-after' => '30'], ''));
            $this->fail('expected SpartRateLimitException');
        } catch (SpartRateLimitException $e) {
            self::assertSame(30, $e->retryAfterSeconds);
            self::assertSame(429, $e->statusCode);
        }
    }

    public function test_429_with_envelope_carries_message(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => ['code' => 'request.rate_limited', 'message' => 'slow down'],
        ]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(429, ['retry-after' => '5'], $body));
            $this->fail('expected SpartRateLimitException');
        } catch (SpartRateLimitException $e) {
            self::assertSame('slow down', $e->getMessage());
            self::assertSame(5, $e->retryAfterSeconds);
        }
    }

    public function test_429_without_retry_after_header_yields_null_seconds(): void
    {
        try {
            ResponseMapper::toIntentResult(new HttpResponse(429, [], ''));
            $this->fail('expected SpartRateLimitException');
        } catch (SpartRateLimitException $e) {
            self::assertNull($e->retryAfterSeconds);
        }
    }

    public function test_500_throws_spart_server_exception(): void
    {
        // 5xx is mapped to SpartServerException (subclass of
        // SpartApiException) so RetryingHttpClient (Task 12) can target
        // server-side failures without re-attempting client-side 4xx.
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => ['code' => 'server.error', 'message' => 'boom'],
        ]);
        try {
            ResponseMapper::toIntentResult(new HttpResponse(500, [], $body));
            $this->fail('expected SpartServerException');
        } catch (SpartServerException $e) {
            self::assertSame(500, $e->statusCode);
            self::assertSame('boom', $e->getMessage());
            self::assertSame('server.error', $e->errorCode);
            // Backward compat: SpartServerException IS-A SpartApiException
            self::assertInstanceOf(SpartApiException::class, $e);
        }
    }

    public function test_503_with_non_json_body_falls_back_to_status_text(): void
    {
        try {
            ResponseMapper::toIntentResult(new HttpResponse(503, [], '<html>oops</html>'));
            $this->fail('expected SpartServerException');
        } catch (SpartServerException $e) {
            self::assertSame(503, $e->statusCode);
            self::assertNotEmpty($e->getMessage());
        }
    }

    public function test_502_throws_spart_server_exception(): void
    {
        // Cover an additional 5xx code to confirm the whole 500-599 range
        // routes to SpartServerException, not just 500/503.
        try {
            ResponseMapper::toIntentResult(new HttpResponse(502, [], ''));
            $this->fail('expected SpartServerException');
        } catch (SpartServerException $e) {
            self::assertSame(502, $e->statusCode);
        }
    }
}
