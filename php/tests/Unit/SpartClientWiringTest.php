<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Http\Curl\CurlClient;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpClientFactory;
use Spart\Sdk\Http\RetryingHttpClient;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\SpartClient;
use Spart\Sdk\SpartClientConfig;

/**
 * Pins the ctor->factory->client wiring so a future refactor can't
 * silently regress the retry feature (e.g., dropping the
 * config->retryPolicy plumb-through to the default factory). Reflection
 * is used because SpartClient does not expose its inner HttpClient
 * publicly — and shouldn't, since that would leak transport details
 * into the SDK's surface.
 */
final class SpartClientWiringTest extends TestCase
{
    public function test_default_factory_uses_RetryingHttpClient_when_policy_enabled(): void
    {
        $cfg = new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k');
        $client = new SpartClient($cfg);
        self::assertInstanceOf(RetryingHttpClient::class, self::extractHttp($client));
    }

    public function test_default_factory_skips_RetryingHttpClient_when_policy_is_none(): void
    {
        $cfg = new SpartClientConfig(
            baseUrl: 'https://api.spart.test',
            apiKey: 'k',
            retryPolicy: RetryPolicy::none(),
        );
        $client = new SpartClient($cfg);
        $http = self::extractHttp($client);
        self::assertInstanceOf(CurlClient::class, $http);
        self::assertNotInstanceOf(RetryingHttpClient::class, $http);
    }

    public function test_caller_supplied_factory_overrides_default_wiring(): void
    {
        // When the caller supplies their own HttpClientFactory, SpartClient
        // MUST honour it verbatim — config->retryPolicy is ignored because
        // the custom factory is the user's chosen escape hatch for transport
        // configuration (e.g. tests injecting a fake HttpClient, or a Guzzle
        // adapter with its own retry middleware).
        $fake = new class implements HttpClient {
            public function send(\Spart\Sdk\Http\HttpRequest $request): \Spart\Sdk\Http\HttpResponse
            {
                throw new \LogicException('not reached');
            }
        };
        $factory = new class ($fake) implements HttpClientFactory {
            public function __construct(private HttpClient $client)
            {
            }
            public function createClient(): HttpClient
            {
                return $this->client;
            }
        };

        $cfg = new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k');
        $client = new SpartClient($cfg, $factory);
        self::assertSame($fake, self::extractHttp($client));
    }

    private static function extractHttp(SpartClient $client): HttpClient
    {
        $ref = (new \ReflectionClass(SpartClient::class))->getProperty('http');
        $ref->setAccessible(true);
        $value = $ref->getValue($client);
        self::assertInstanceOf(HttpClient::class, $value);
        return $value;
    }
}
