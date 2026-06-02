<?php

declare(strict_types=1);

namespace Spart\Sdk;

use Spart\Sdk\Internal\UserAgentValidator;
use Spart\Sdk\Retry\RetryPolicy;

/** @final */
final class SpartClientConfig
{
    public readonly string $baseUrl;
    public readonly RetryPolicy $retryPolicy;
    public readonly string $userAgent;

    public function __construct(
        string $baseUrl,
        public readonly string $apiKey,
        public readonly int $timeoutSeconds = 30,
        ?RetryPolicy $retryPolicy = null,
        ?string $userAgent = null,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('SpartClientConfig::apiKey must not be blank.');
        }
        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException(
                'SpartClientConfig::timeoutSeconds must be >= 1 (cURL treats 0 as no timeout).'
            );
        }

        $parts = parse_url($baseUrl);
        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new \InvalidArgumentException(
                'SpartClientConfig::baseUrl must be an absolute http or https URL with no userinfo, query, or fragment.'
            );
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->retryPolicy = $retryPolicy ?? RetryPolicy::default();
        // Centralized in UserAgentValidator so all three construction
        // paths (this, CurlClient, CurlHttpClientFactory) share one
        // ruleset and one default-string source-of-truth.
        $this->userAgent = UserAgentValidator::validate($userAgent, 'SpartClientConfig::userAgent');
    }
}
