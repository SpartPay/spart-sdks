<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Http;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Http\Curl\CurlClient;
use Spart\Sdk\Http\Curl\CurlHttpClientFactory;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\RetryingHttpClient;
use Spart\Sdk\Retry\RetryPolicy;
use Spart\Sdk\Retry\Sleeper;
use Spart\Sdk\Retry\UsleepSleeper;

final class CurlHttpClientFactoryTest extends TestCase
{
    public function test_createClient_returns_curl_client_instance(): void
    {
        $client = (new CurlHttpClientFactory())->createClient();
        self::assertInstanceOf(HttpClient::class, $client);
        self::assertInstanceOf(CurlClient::class, $client);
    }

    public function test_createClient_returns_plain_curl_client_when_policy_is_none(): void
    {
        $factory = new CurlHttpClientFactory(RetryPolicy::none());
        $client = $factory->createClient();
        self::assertInstanceOf(CurlClient::class, $client);
        self::assertNotInstanceOf(RetryingHttpClient::class, $client);
    }

    public function test_createClient_wraps_with_retrying_client_when_policy_has_retries(): void
    {
        $factory = new CurlHttpClientFactory(RetryPolicy::default());
        $client = $factory->createClient();
        self::assertInstanceOf(RetryingHttpClient::class, $client);
    }

    public function test_createClient_uses_provided_sleeper_when_supplied(): void
    {
        $sleeper = new class implements Sleeper {
            public int $callCount = 0;
            public function sleepMs(int $ms): void
            {
                $this->callCount++;
            }
        };
        $factory = new CurlHttpClientFactory(RetryPolicy::default(), $sleeper);
        $client = $factory->createClient();
        // Reach in via reflection to confirm the injected sleeper is actually
        // wired through; without this check, the ctor arg could be silently
        // dropped and we'd fall back to UsleepSleeper without anyone noticing.
        $sleeperRef = (new \ReflectionClass(RetryingHttpClient::class))->getProperty('sleeper');
        $sleeperRef->setAccessible(true);
        self::assertSame($sleeper, $sleeperRef->getValue($client));
    }

    public function test_createClient_defaults_sleeper_to_usleep_sleeper(): void
    {
        $factory = new CurlHttpClientFactory(RetryPolicy::default());
        $client = $factory->createClient();
        $sleeperRef = (new \ReflectionClass(RetryingHttpClient::class))->getProperty('sleeper');
        $sleeperRef->setAccessible(true);
        self::assertInstanceOf(UsleepSleeper::class, $sleeperRef->getValue($client));
    }

    public function test_createClient_defaults_user_agent_when_omitted(): void
    {
        $factory = new CurlHttpClientFactory();
        $client = $factory->createClient();
        self::assertInstanceOf(CurlClient::class, $client);

        $uaRef = (new \ReflectionClass(CurlClient::class))->getProperty('userAgent');
        $uaRef->setAccessible(true);
        self::assertSame('spart-php-sdk/1.0', $uaRef->getValue($client));
    }

    public function test_createClient_threads_user_agent_to_curl_client(): void
    {
        $factory = new CurlHttpClientFactory(userAgent: 'MyMerchant/3.0 (PHP)');
        $client = $factory->createClient();
        self::assertInstanceOf(CurlClient::class, $client);

        $uaRef = (new \ReflectionClass(CurlClient::class))->getProperty('userAgent');
        $uaRef->setAccessible(true);
        self::assertSame('MyMerchant/3.0 (PHP)', $uaRef->getValue($client));
    }

    public function test_createClient_threads_user_agent_through_retry_decorator(): void
    {
        // Even when the factory wraps CurlClient with RetryingHttpClient,
        // the underlying CurlClient must still carry the UA — otherwise
        // retries would silently fall back to the default UA.
        $factory = new CurlHttpClientFactory(
            policy: RetryPolicy::default(),
            userAgent: 'MyMerchant/4.0',
        );
        $client = $factory->createClient();
        self::assertInstanceOf(RetryingHttpClient::class, $client);

        $innerRef = (new \ReflectionClass(RetryingHttpClient::class))->getProperty('inner');
        $innerRef->setAccessible(true);
        $inner = $innerRef->getValue($client);
        self::assertInstanceOf(CurlClient::class, $inner);

        $uaRef = (new \ReflectionClass(CurlClient::class))->getProperty('userAgent');
        $uaRef->setAccessible(true);
        self::assertSame('MyMerchant/4.0', $uaRef->getValue($inner));
    }

    // --- Constructor validation: this factory is a public class that can
    //     be constructed independently of SpartClientConfig (e.g. injected
    //     directly into SpartClient via its second arg). The full UA rule
    //     set lives in UserAgentValidatorTest; these tests just prove the
    //     validator is wired into this construction path.

    public function test_constructor_rejects_blank_user_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlHttpClientFactory(userAgent: '   ');
    }

    public function test_constructor_rejects_user_agent_with_carriage_return(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlHttpClientFactory(userAgent: "MyApp/1.0\r\nX-Injected: yes");
    }

    public function test_constructor_rejects_user_agent_with_line_feed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlHttpClientFactory(userAgent: "MyApp/1.0\nX-Injected: yes");
    }

    public function test_constructor_rejects_user_agent_with_null_byte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlHttpClientFactory(userAgent: "MyApp/1.0\0junk");
    }

    public function test_constructor_rejects_oversized_user_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CurlHttpClientFactory(userAgent: str_repeat('a', 1025));
    }
}
