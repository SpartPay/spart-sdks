<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\SpartClientConfig;

final class SpartClientConfigTest extends TestCase
{
    public function test_default_base_url_constant_is_spartpay_production(): void
    {
        self::assertSame('https://api.spartpay.com', SpartClientConfig::DEFAULT_BASE_URL);
    }

    public function test_base_url_defaults_to_spartpay_production_when_omitted(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k');
        self::assertSame('https://api.spartpay.com', $cfg->baseUrl);
    }

    public function test_explicit_base_url_overrides_default(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com');
        self::assertSame('https://api.example.com', $cfg->baseUrl);
    }

    public function test_constructor_rejects_scheme_only_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpartClientConfig(apiKey: 'k', baseUrl: 'http://');
    }

    public function test_constructor_rejects_blank_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpartClientConfig(apiKey: '   ', baseUrl: 'https://api.example.com');
    }

    public function test_constructor_accepts_https_base_url(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com');
        self::assertSame('https://api.example.com', $cfg->baseUrl);
    }

    public function test_constructor_strips_trailing_slash(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com/');
        self::assertSame('https://api.example.com', $cfg->baseUrl);
    }

    public function test_constructor_rejects_non_http_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http or https');
        new SpartClientConfig(apiKey: 'k', baseUrl: 'ftp://api.example.com');
    }

    public function test_constructor_rejects_url_with_query(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com?x=1');
    }

    public function test_constructor_rejects_url_with_fragment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com#frag');
    }

    public function test_constructor_rejects_url_with_userinfo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpartClientConfig(apiKey: 'k', baseUrl: 'https://user:pwd@api.example.com');
    }

    public function test_constructor_rejects_url_without_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpartClientConfig(apiKey: 'k', baseUrl: 'http://?');
    }

    public function test_constructor_rejects_zero_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('timeoutSeconds');
        new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com', timeoutSeconds: 0);
    }

    public function test_constructor_rejects_negative_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('timeoutSeconds');
        new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com', timeoutSeconds: -1);
    }

    public function test_constructor_accepts_minimum_timeout(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com', timeoutSeconds: 1);
        self::assertSame(1, $cfg->timeoutSeconds);
    }

    public function test_default_retry_policy_is_RetryPolicy_default(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com');
        self::assertSame(3, $cfg->retryPolicy->maxRetries);
        self::assertSame(200, $cfg->retryPolicy->baseDelayMs);
        self::assertSame(5000, $cfg->retryPolicy->maxDelayMs);
        self::assertTrue($cfg->retryPolicy->jitter);
    }

    public function test_constructor_accepts_custom_retry_policy(): void
    {
        $cfg = new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            retryPolicy: RetryPolicy::none(),
        );
        self::assertSame(0, $cfg->retryPolicy->maxRetries);
    }

    public function test_constructor_accepts_explicit_null_retry_policy_and_falls_back_to_default(): void
    {
        // Explicitly passing null should be treated the same as omitting the
        // argument: caller signalled "I don't care, give me the default" and
        // the SDK applies sensible retry semantics.
        $cfg = new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            retryPolicy: null,
        );
        self::assertSame(3, $cfg->retryPolicy->maxRetries);
    }

    // -------------------------------------------------------------------------
    // userAgent
    // -------------------------------------------------------------------------

    public function test_default_user_agent_is_spart_php_sdk_v1(): void
    {
        $cfg = new SpartClientConfig(apiKey: 'k', baseUrl: 'https://api.example.com');
        self::assertSame('spart-php-sdk/1.0', $cfg->userAgent);
    }

    public function test_constructor_accepts_explicit_null_user_agent_and_falls_back_to_default(): void
    {
        $cfg = new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: null,
        );
        self::assertSame('spart-php-sdk/1.0', $cfg->userAgent);
    }

    public function test_constructor_accepts_custom_user_agent(): void
    {
        $cfg = new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: 'MyMerchant/2.3.4 (PHP 8.1; CMS=ExampleShop)',
        );
        self::assertSame('MyMerchant/2.3.4 (PHP 8.1; CMS=ExampleShop)', $cfg->userAgent);
    }

    public function test_constructor_rejects_blank_user_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('userAgent');
        new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: '   ',
        );
    }

    public function test_constructor_rejects_user_agent_with_carriage_return(): void
    {
        // Header injection guard: CR/LF in a HTTP header value can let
        // a malicious caller inject extra headers downstream.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('userAgent');
        new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: "MyApp/1.0\r\nX-Injected: yes",
        );
    }

    public function test_constructor_rejects_user_agent_with_line_feed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('userAgent');
        new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: "MyApp/1.0\nX-Injected: yes",
        );
    }

    public function test_constructor_rejects_user_agent_with_null_byte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('userAgent');
        new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: "MyApp/1.0\0junk",
        );
    }

    public function test_constructor_rejects_user_agent_exceeding_max_length(): void
    {
        // 1024 bytes is generous (covers even verbose multi-product UAs)
        // and well under the 8 KB single-header limit most upstream
        // proxies enforce.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('userAgent');
        new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: str_repeat('a', 1025),
        );
    }

    public function test_constructor_accepts_user_agent_at_max_length(): void
    {
        $ua = str_repeat('a', 1024);
        $cfg = new SpartClientConfig(
            apiKey: 'k',
            baseUrl: 'https://api.example.com',
            userAgent: $ua,
        );
        self::assertSame($ua, $cfg->userAgent);
    }
}
