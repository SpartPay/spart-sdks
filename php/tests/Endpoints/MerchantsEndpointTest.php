<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Endpoints;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\SpartClient;
use Spart\Sdk\SpartClientConfig;
use Spart\Sdk\Tests\Support\FakeHttpClient;
use Spart\Sdk\Tests\Support\FakeHttpClientFactory;

final class MerchantsEndpointTest extends TestCase
{
    public function test_eligibility_sends_get_to_api_merchants_eligibility_with_merchant_api_key_header(): void
    {
        $envelope = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['eligible' => true, 'reasons' => []],
        ]);
        $http = new FakeHttpClient([
            new HttpResponse(200, ['content-type' => 'application/json'], $envelope),
        ]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'sk_test_abc'),
            new FakeHttpClientFactory($http),
        );

        $result = $client->merchants()->eligibility();

        self::assertTrue($result->eligible);
        self::assertSame([], $result->reasons);
        self::assertCount(1, $http->requests);

        $sent = $http->requests[0];
        self::assertSame('GET', $sent->method);
        self::assertSame('https://api.spart.test/api/merchants/eligibility', $sent->url);
        self::assertSame('sk_test_abc', $sent->headers['x-spart-merchant-api-key']);
        self::assertArrayNotHasKey('Authorization', $sent->headers);
        self::assertSame('application/json', $sent->headers['Accept']);
        self::assertNull($sent->body);
    }

    public function test_eligibility_strips_trailing_slash_from_base_url(): void
    {
        $envelope = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['eligible' => true, 'reasons' => []],
        ]);
        $http = new FakeHttpClient([new HttpResponse(200, [], $envelope)]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test/', apiKey: 'k'),
            new FakeHttpClientFactory($http),
        );
        $client->merchants()->eligibility();
        self::assertSame('https://api.spart.test/api/merchants/eligibility', $http->requests[0]->url);
    }

    public function test_eligibility_with_false_response_returns_reasons(): void
    {
        $envelope = (string) json_encode([
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
        $http = new FakeHttpClient([new HttpResponse(200, [], $envelope)]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k'),
            new FakeHttpClientFactory($http),
        );

        $result = $client->merchants()->eligibility();

        self::assertFalse($result->eligible);
        self::assertCount(1, $result->reasons);
        self::assertSame('merchant.not_connected_to_stripe', $result->reasons[0]->code);
    }

    public function test_eligibility_propagates_auth_exception_on_401(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => false,
            'error' => ['code' => 'auth.unauthorized'],
        ]);
        $http = new FakeHttpClient([new HttpResponse(401, [], $body)]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k'),
            new FakeHttpClientFactory($http),
        );
        $this->expectException(SpartAuthException::class);
        $client->merchants()->eligibility();
    }

    public function test_eligibility_passes_configured_timeout_to_request(): void
    {
        $envelope = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['eligible' => true, 'reasons' => []],
        ]);
        $http = new FakeHttpClient([new HttpResponse(200, [], $envelope)]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k', timeoutSeconds: 7),
            new FakeHttpClientFactory($http),
        );
        $client->merchants()->eligibility();
        self::assertSame(7, $http->requests[0]->timeoutSeconds);
    }
}
