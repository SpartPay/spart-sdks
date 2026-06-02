<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Support;

use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpClientFactory;

final class FakeHttpClientFactory implements HttpClientFactory
{
    public function __construct(public readonly FakeHttpClient $client)
    {
    }

    public function createClient(): HttpClient
    {
        return $this->client;
    }
}
