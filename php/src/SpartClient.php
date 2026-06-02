<?php

declare(strict_types=1);

namespace Spart\Sdk;

use Spart\Sdk\Http\Curl\CurlHttpClientFactory;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpClientFactory;
use Spart\Sdk\Endpoints\IntentsEndpoint;
use Spart\Sdk\Endpoints\MerchantsEndpoint;

/** @final */
final class SpartClient
{
    private readonly HttpClient $http;

    public function __construct(
        public readonly SpartClientConfig $config,
        ?HttpClientFactory $factory = null,
    ) {
        $factory ??= new CurlHttpClientFactory(
            policy: $config->retryPolicy,
            userAgent: $config->userAgent,
        );
        $this->http = $factory->createClient();
    }

    public function intents(): IntentsEndpoint
    {
        return new IntentsEndpoint($this->config, $this->http);
    }

    public function merchants(): MerchantsEndpoint
    {
        return new MerchantsEndpoint($this->config, $this->http);
    }
}
