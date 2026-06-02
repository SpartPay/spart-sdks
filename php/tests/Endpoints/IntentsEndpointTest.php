<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Endpoints;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\CreateIntentRequest;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Models\Contact;
use Spart\Sdk\Models\LineItem;
use Spart\Sdk\Models\Money;
use Spart\Sdk\SpartClient;
use Spart\Sdk\SpartClientConfig;
use Spart\Sdk\Tests\Support\FakeHttpClient;
use Spart\Sdk\Tests\Support\FakeHttpClientFactory;

final class IntentsEndpointTest extends TestCase
{
    public function test_create_sends_post_to_api_intents_with_merchant_api_key_header(): void
    {
        $envelope = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['intentShortId' => 'abc', 'checkoutUrl' => 'https://x/c'],
        ]);
        $http = new FakeHttpClient([
            new HttpResponse(201, ['content-type' => 'application/json'], $envelope),
        ]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'sk_test_abc'),
            new FakeHttpClientFactory($http),
        );

        $req = new CreateIntentRequest(
            total: Money::fromString('10.00', 'EUR'),
            lineItems: [new LineItem('Socks', 2)],
            sparter: new Contact('a@b.com'),
            sessionId: 'wc_42',
        );
        $result = $client->intents()->create($req);

        self::assertSame('abc', $result->intentShortId);
        self::assertSame('https://x/c', $result->checkoutUrl);
        self::assertFalse($result->wasIdempotentReplay);
        self::assertCount(1, $http->requests);

        $sent = $http->requests[0];
        self::assertSame('POST', $sent->method);
        self::assertSame('https://api.spart.test/api/intents', $sent->url);
        self::assertSame('sk_test_abc', $sent->headers['x-spart-merchant-api-key']);
        self::assertArrayNotHasKey('Authorization', $sent->headers);
        self::assertSame('application/json', $sent->headers['Content-Type']);
        self::assertSame('application/json', $sent->headers['Accept']);
        self::assertNotNull($sent->body);
        self::assertStringContainsString('"value":10.00', $sent->body);
        self::assertStringContainsString('"currency":"EUR"', $sent->body);
        self::assertStringContainsString('"sparter"', $sent->body);
    }

    public function test_create_strips_trailing_slash_from_base_url(): void
    {
        $envelope = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['intentShortId' => 'abc', 'checkoutUrl' => 'https://x/c'],
        ]);
        $http = new FakeHttpClient([new HttpResponse(201, [], $envelope)]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test/', apiKey: 'k'),
            new FakeHttpClientFactory($http),
        );
        $client->intents()->create(self::sampleRequest());
        self::assertSame('https://api.spart.test/api/intents', $http->requests[0]->url);
    }

    public function test_create_marks_200_response_as_idempotent_replay(): void
    {
        $envelope = (string) json_encode([
            'isSuccessful' => true,
            'value' => ['intentShortId' => 'abc', 'checkoutUrl' => 'https://x/c'],
        ]);
        $http = new FakeHttpClient([new HttpResponse(200, [], $envelope)]);
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k'),
            new FakeHttpClientFactory($http),
        );
        $result = $client->intents()->create(self::sampleRequest());
        self::assertTrue($result->wasIdempotentReplay);
    }

    private static function sampleRequest(): CreateIntentRequest
    {
        return new CreateIntentRequest(
            total: Money::fromString('1.00', 'EUR'),
            lineItems: [new LineItem('I', 1)],
            sparter: new Contact('a@b.com'),
        );
    }
}
