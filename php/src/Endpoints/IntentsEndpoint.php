<?php

declare(strict_types=1);

namespace Spart\Sdk\Endpoints;

use Spart\Sdk\Dtos\CreateIntentRequest;
use Spart\Sdk\Dtos\IntentDetails;
use Spart\Sdk\Dtos\IntentList;
use Spart\Sdk\Dtos\IntentResult;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Internal\JsonEncoder;
use Spart\Sdk\Internal\ResponseMapper;
use Spart\Sdk\SpartClientConfig;

/** @final */
final class IntentsEndpoint
{
    private const URL_PATH = '/api/intents';
    private const API_KEY_HEADER = 'x-spart-merchant-api-key';

    public function __construct(
        private readonly SpartClientConfig $config,
        private readonly HttpClient $http,
    ) {
    }

    public function create(CreateIntentRequest $request): IntentResult
    {
        $payload = JsonEncoder::encode($request->toArray());
        $http = new HttpRequest(
            method: 'POST',
            url: $this->config->baseUrl . self::URL_PATH,
            headers: [
                self::API_KEY_HEADER => $this->config->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            body: $payload,
            timeoutSeconds: $this->config->timeoutSeconds,
        );
        $response = $this->http->send($http);
        return ResponseMapper::toIntentResult($response);
    }

    /**
     * Reads the full intent projection by its short id. Throws
     * {@see \Spart\Sdk\Exceptions\SpartApiException} (typically with
     * statusCode 404) if the intent does not exist or belongs to a
     * different merchant — the server returns 404 in both cases so
     * the SDK does NOT distinguish them.
     */
    public function get(string $shortId): IntentDetails
    {
        if (trim($shortId) === '') {
            throw new \InvalidArgumentException('IntentsEndpoint::get(): shortId must not be blank.');
        }
        $http = new HttpRequest(
            method: 'GET',
            url: $this->config->baseUrl . self::URL_PATH . '/' . rawurlencode($shortId),
            headers: [
                self::API_KEY_HEADER => $this->config->apiKey,
                'Accept' => 'application/json',
            ],
            body: null,
            timeoutSeconds: $this->config->timeoutSeconds,
        );
        $response = $this->http->send($http);
        return ResponseMapper::toIntentDetails($response);
    }

    /**
     * Lists intents for the authenticated merchant, paged.
     *
     * Server-side clamps: `page` is clamped to >= 1; `pageSize` is
     * clamped to 1..100. Negative or oversized values are not an error;
     * the server silently coerces them and the response's `page` /
     * `requestedPageSize` reflect the clamped values.
     *
     * `includeCompleted=false` (the default) returns only intents that
     * have NOT yet been turned into orders.
     */
    public function list(int $page = 1, int $pageSize = 10, bool $includeCompleted = false): IntentList
    {
        $query = http_build_query([
            'page' => $page,
            'pageSize' => $pageSize,
            'includeCompleted' => $includeCompleted ? 'true' : 'false',
        ]);
        $http = new HttpRequest(
            method: 'GET',
            url: $this->config->baseUrl . self::URL_PATH . '?' . $query,
            headers: [
                self::API_KEY_HEADER => $this->config->apiKey,
                'Accept' => 'application/json',
            ],
            body: null,
            timeoutSeconds: $this->config->timeoutSeconds,
        );
        $response = $this->http->send($http);
        return ResponseMapper::toIntentList($response);
    }
}
