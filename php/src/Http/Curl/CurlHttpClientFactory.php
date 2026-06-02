<?php

declare(strict_types=1);

namespace Spart\Sdk\Http\Curl;

use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpClientFactory;
use Spart\Sdk\Http\RetryingHttpClient;
use Spart\Sdk\Internal\UserAgentValidator;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\Retry\Sleeper;
use Spart\Sdk\Retry\UsleepSleeper;

/**
 * Default HttpClientFactory: produces a CurlClient, optionally wrapped
 * with RetryingHttpClient when the supplied RetryPolicy permits at
 * least one retry. When the policy is RetryPolicy::none() (or no policy
 * is supplied) the bare CurlClient is returned so the SDK does NOT pay
 * the decorator cost when retries are explicitly disabled.
 *
 * The Sleeper is injectable so tests can swap UsleepSleeper for a
 * recording or null implementation. Wired through SpartClientConfig
 * by default — see SpartClient::__construct.
 *
 * @final
 */
final class CurlHttpClientFactory implements HttpClientFactory
{
    private readonly string $userAgent;

    public function __construct(
        private readonly ?RetryPolicy $policy = null,
        private readonly ?Sleeper $sleeper = null,
        ?string $userAgent = null,
    ) {
        // Defense-in-depth. This factory can be constructed directly
        // (without going through SpartClientConfig), so the same UA
        // rules MUST apply on this construction path too. Centralized
        // in UserAgentValidator so all three callers share one ruleset
        // and one default-string source-of-truth.
        $this->userAgent = UserAgentValidator::validate($userAgent, 'CurlHttpClientFactory::userAgent');
    }

    public function createClient(): HttpClient
    {
        $client = new CurlClient(userAgent: $this->userAgent);
        if ($this->policy === null || $this->policy->maxRetries === 0) {
            return $client;
        }
        return new RetryingHttpClient(
            $client,
            $this->policy,
            $this->sleeper ?? new UsleepSleeper(),
        );
    }
}
