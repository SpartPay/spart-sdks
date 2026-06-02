<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Exceptions\SpartException;
use Spart\Sdk\Exceptions\SpartRateLimitException;
use Spart\Sdk\Exceptions\SpartServerException;
use Spart\Sdk\Exceptions\SpartTimeoutException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Exceptions\SpartValidationException;

final class ExceptionHierarchyTest extends TestCase
{
    public function test_all_extend_base(): void
    {
        self::assertTrue(is_subclass_of(SpartTransportException::class, SpartException::class));
        self::assertTrue(is_subclass_of(SpartApiException::class, SpartException::class));
        self::assertTrue(is_subclass_of(SpartAuthException::class, SpartApiException::class));
        self::assertTrue(is_subclass_of(SpartValidationException::class, SpartApiException::class));
        self::assertTrue(is_subclass_of(SpartRateLimitException::class, SpartApiException::class));
        self::assertTrue(is_subclass_of(SpartTimeoutException::class, SpartTransportException::class));
        self::assertTrue(is_subclass_of(SpartServerException::class, SpartApiException::class));
    }

    public function test_api_exception_carries_status_error_code_details_and_raw_body(): void
    {
        $e = new SpartApiException(
            'boom',
            statusCode: 500,
            errorCode: 'server.error',
            errorDetails: ['code-x', 'try again'],
            rawBody: ['k' => 'v'],
        );
        self::assertSame(500, $e->statusCode);
        self::assertSame('server.error', $e->errorCode);
        self::assertSame(['code-x', 'try again'], $e->errorDetails);
        self::assertSame(['k' => 'v'], $e->rawBody);
    }

    public function test_api_exception_defaults_error_code_and_details_to_null_and_empty_list(): void
    {
        $e = new SpartApiException('boom', statusCode: 500);
        self::assertNull($e->errorCode);
        self::assertSame([], $e->errorDetails);
    }

    public function test_rate_limit_carries_retry_after_code_and_details(): void
    {
        $e = new SpartRateLimitException(
            'slow down',
            retryAfterSeconds: 30,
            errorCode: 'request.rate_limited',
            errorDetails: ['rate-limit-exceeded'],
        );
        self::assertSame(30, $e->retryAfterSeconds);
        self::assertSame(429, $e->statusCode);
        self::assertSame('request.rate_limited', $e->errorCode);
        self::assertSame(['rate-limit-exceeded'], $e->errorDetails);
    }

    public function test_auth_exception_status_is_401(): void
    {
        $e = new SpartAuthException('nope', errorCode: 'auth.unauthorized', errorDetails: ['invalid-key']);
        self::assertSame(401, $e->statusCode);
        self::assertSame('auth.unauthorized', $e->errorCode);
        self::assertSame(['invalid-key'], $e->errorDetails);
    }

    public function test_validation_exception_default_status_is_null(): void
    {
        // SpartValidationException is also thrown for *local* validation failures
        // (e.g. webhook signature verification, malformed envelopes parsed
        // before any HTTP call). In those cases the statusCode must NOT be
        // a fake "400" — that misleads consumers who branch on it.
        self::assertNull((new SpartValidationException('local failure'))->statusCode);
    }

    public function test_validation_exception_carries_status_code_and_details_when_provided(): void
    {
        $e = new SpartValidationException(
            'bad input',
            statusCode: 400,
            errorCode: 'intent.session_conflict',
            errorDetails: ['session id already used'],
            rawBody: ['k' => 'v'],
        );
        self::assertSame(400, $e->statusCode);
        self::assertSame('intent.session_conflict', $e->errorCode);
        self::assertSame(['session id already used'], $e->errorDetails);
        self::assertSame(['k' => 'v'], $e->rawBody);
    }

    public function test_transport_exception_wraps_previous(): void
    {
        $cause = new \RuntimeException('curl failed');
        $e = new SpartTransportException('Network error', 0, $cause);
        self::assertSame('Network error', $e->getMessage());
        self::assertSame($cause, $e->getPrevious());
        self::assertInstanceOf(\Throwable::class, $e);
    }

    public function test_api_exception_preserves_previous(): void
    {
        $cause = new \JsonException('bad json');
        $e = new SpartApiException(
            'parse failed',
            statusCode: 502,
            errorCode: null,
            errorDetails: [],
            rawBody: null,
            previous: $cause,
        );
        self::assertSame($cause, $e->getPrevious());
        self::assertSame(502, $e->statusCode);
    }

    public function test_timeout_exception_is_a_transport_exception(): void
    {
        // Catching SpartTransportException must continue to catch timeouts;
        // this is the contract that lets existing callers' transport-error
        // handling keep working unchanged after the retry feature lands.
        $e = new SpartTimeoutException('timed out');
        self::assertInstanceOf(SpartTransportException::class, $e);
        self::assertInstanceOf(SpartException::class, $e);
        self::assertSame('timed out', $e->getMessage());
    }

    public function test_timeout_exception_carries_previous_throwable(): void
    {
        $cause = new \RuntimeException('underlying curl');
        $e = new SpartTimeoutException('timed out', 0, $cause);
        self::assertSame($cause, $e->getPrevious());
    }

    public function test_server_exception_is_an_api_exception_with_status(): void
    {
        $e = new SpartServerException('boom', statusCode: 503);
        self::assertInstanceOf(SpartApiException::class, $e);
        self::assertInstanceOf(SpartException::class, $e);
        self::assertSame(503, $e->statusCode);
    }

    public function test_server_exception_carries_error_code_details_and_raw_body(): void
    {
        $e = new SpartServerException(
            'unavailable',
            statusCode: 503,
            errorCode: 'server.error',
            errorDetails: ['service degraded', 'try again later'],
            rawBody: ['error' => 'unavailable'],
        );
        self::assertSame('server.error', $e->errorCode);
        self::assertSame(['service degraded', 'try again later'], $e->errorDetails);
        self::assertSame(['error' => 'unavailable'], $e->rawBody);
    }
}
