<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Internal\UserAgentValidator;

/**
 * Locks the validation contract that every SDK construction path
 * accepting a User-Agent (SpartClientConfig, CurlClient,
 * CurlHttpClientFactory) MUST share. Drift between paths is a
 * correctness AND a security concern (CR/LF in the UA = HTTP header
 * injection), so this single suite is the source of truth — the per-
 * surface tests just assert "validator was applied".
 */
final class UserAgentValidatorTest extends TestCase
{
    public function test_default_constant_is_spart_php_sdk_v1(): void
    {
        // Drift guard. The default UA is referenced by name in
        // PR descriptions, server log dashboards, and merchant
        // analytics — silently changing it here would break those
        // downstream consumers.
        self::assertSame('spart-php-sdk/1.0', UserAgentValidator::DEFAULT_USER_AGENT);
    }

    public function test_max_bytes_constant_is_1024(): void
    {
        // Generous (covers verbose multi-product UAs) but well under
        // the 8 KB single-header limit most upstream proxies enforce.
        self::assertSame(1024, UserAgentValidator::USER_AGENT_MAX_BYTES);
    }

    public function test_validate_returns_default_when_null(): void
    {
        self::assertSame(
            UserAgentValidator::DEFAULT_USER_AGENT,
            UserAgentValidator::validate(null)
        );
    }

    public function test_validate_returns_caller_value_when_valid(): void
    {
        $ua = 'MyMerchant/2.3.4 (PHP 8.1; CMS=ExampleShop)';
        self::assertSame($ua, UserAgentValidator::validate($ua));
    }

    public function test_validate_throws_on_blank(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserAgentValidator::validate('');
    }

    public function test_validate_throws_on_whitespace_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserAgentValidator::validate("   \t");
    }

    public function test_validate_throws_on_carriage_return(): void
    {
        // The whole point of this validator: a CR in CURLOPT_USERAGENT
        // smuggles an additional header line into the outbound request.
        $this->expectException(\InvalidArgumentException::class);
        UserAgentValidator::validate("MyApp/1.0\r\nX-Injected: yes");
    }

    public function test_validate_throws_on_line_feed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserAgentValidator::validate("MyApp/1.0\nX-Injected: yes");
    }

    public function test_validate_throws_on_null_byte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserAgentValidator::validate("MyApp/1.0\0junk");
    }

    public function test_validate_throws_on_oversized(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserAgentValidator::validate(str_repeat('a', UserAgentValidator::USER_AGENT_MAX_BYTES + 1));
    }

    public function test_validate_accepts_at_max_size(): void
    {
        $ua = str_repeat('a', UserAgentValidator::USER_AGENT_MAX_BYTES);
        self::assertSame($ua, UserAgentValidator::validate($ua));
    }

    public function test_validate_context_string_appears_in_exception_message(): void
    {
        try {
            UserAgentValidator::validate('', 'CurlClient::userAgent');
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('CurlClient::userAgent', $e->getMessage());
        }
    }

    public function test_validate_default_context_is_user_agent(): void
    {
        // Backwards-compat with the existing SpartClientConfig error
        // messages which use the bare "userAgent" identifier.
        try {
            UserAgentValidator::validate('');
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('userAgent', $e->getMessage());
        }
    }
}
