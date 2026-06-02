<?php

declare(strict_types=1);

namespace Spart\Sdk\Internal;

/**
 * Centralized validation for HTTP User-Agent values across every SDK
 * construction path that accepts one (SpartClientConfig, CurlClient,
 * CurlHttpClientFactory). Single source of truth for both the
 * validation rules AND the SDK default UA string so they cannot drift.
 *
 * Validation rules:
 *   - Non-blank after trim (caller always meant something concrete).
 *   - No CR / LF / NULL bytes — header-injection guard so a hostile
 *     value cannot smuggle additional outbound headers via
 *     CURLOPT_USERAGENT (which cURL splices verbatim into the
 *     `User-Agent:` line).
 *   - Length capped at USER_AGENT_MAX_BYTES — generous (covers verbose
 *     multi-product UAs) but well under the 8 KB single-header limit
 *     most upstream proxies / CDNs enforce.
 *
 * @internal Not part of the SDK's public BC promise. External callers
 *           should configure UAs through SpartClientConfig.
 */
final class UserAgentValidator
{
    public const DEFAULT_USER_AGENT = 'spart-php-sdk/1.0';
    public const USER_AGENT_MAX_BYTES = 1024;

    /**
     * Returns the validated UA, substituting DEFAULT_USER_AGENT for null.
     *
     * @param ?string $userAgent  Raw caller-supplied value (or null to
     *                            request the SDK default).
     * @param string  $context    Identifier included in exception messages
     *                            (e.g. 'CurlClient::userAgent') so the
     *                            failing surface is obvious in logs.
     *
     * @throws \InvalidArgumentException on blank, CR/LF/NULL bytes, or
     *                                   value exceeding USER_AGENT_MAX_BYTES.
     */
    public static function validate(?string $userAgent, string $context = 'userAgent'): string
    {
        if ($userAgent === null) {
            return self::DEFAULT_USER_AGENT;
        }
        if (trim($userAgent) === '') {
            throw new \InvalidArgumentException(
                "{$context} must not be blank when provided."
            );
        }
        if (strpbrk($userAgent, "\r\n\0") !== false) {
            throw new \InvalidArgumentException(
                "{$context} must not contain CR, LF, or NULL bytes."
            );
        }
        if (strlen($userAgent) > self::USER_AGENT_MAX_BYTES) {
            throw new \InvalidArgumentException(
                "{$context} must not exceed " . self::USER_AGENT_MAX_BYTES . ' bytes.'
            );
        }
        return $userAgent;
    }
}
