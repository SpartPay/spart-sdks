<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Endpoints\MerchantsEndpoint;
use Spart\Sdk\SpartClient;
use Spart\Sdk\SpartClientConfig;
use Spart\Sdk\Tests\Support\FakeHttpClient;
use Spart\Sdk\Tests\Support\FakeHttpClientFactory;

final class SpartClientMerchantsAccessorTest extends TestCase
{
    public function test_merchants_returns_merchants_endpoint_instance(): void
    {
        $client = new SpartClient(
            new SpartClientConfig(baseUrl: 'https://api.spart.test', apiKey: 'k'),
            new FakeHttpClientFactory(new FakeHttpClient([])),
        );
        self::assertInstanceOf(MerchantsEndpoint::class, $client->merchants());
    }
}
