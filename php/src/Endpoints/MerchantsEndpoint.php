<?php

declare(strict_types=1);

namespace Spart\Sdk\Endpoints;

use Spart\Sdk\Dtos\Eligibility;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Internal\ResponseMapper;
use Spart\Sdk\SpartClientConfig;

/** @final */
final class MerchantsEndpoint
{
    private const URL_PATH = '/api/merchants/eligibility';
    private const API_KEY_HEADER = 'x-spart-merchant-api-key';

    public function __construct(
        private readonly SpartClientConfig $config,
        private readonly HttpClient $http,
    ) {
    }

    /**
     * Reads the merchant's current eligibility to start payment intents.
     *
     * On success returns {@see Eligibility} where `eligible` is true with
     * empty `reasons`, or false with one or more {@see \Spart\Sdk\Dtos\EligibilityReason}
     * describing the blocking conditions. Failure modes (401/404/500/etc.)
     * are mapped to typed Spart exceptions via {@see ResponseMapper}.
     */
    public function eligibility(): Eligibility
    {
        $http = new HttpRequest(
            method: 'GET',
            url: $this->config->baseUrl . self::URL_PATH,
            headers: [
                self::API_KEY_HEADER => $this->config->apiKey,
                'Accept' => 'application/json',
            ],
            body: null,
            timeoutSeconds: $this->config->timeoutSeconds,
        );
        $response = $this->http->send($http);
        return ResponseMapper::toEligibility($response);
    }
}
