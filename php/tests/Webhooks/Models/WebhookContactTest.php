<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookContact;

/**
 * Locks down the wire shape of {fullName: string, email: string}.
 *
 * The server composes fullName from first+last name (falling back to
 * email when both are blank). The SDK does not need to replicate
 * that policy; it just consumes the result.
 */
final class WebhookContactTest extends TestCase
{
    public function test_happy_path(): void
    {
        $c = WebhookContact::fromArray(['fullName' => 'Alice Smith', 'email' => 'alice@example.com']);
        self::assertSame('Alice Smith', $c->fullName);
        self::assertSame('alice@example.com', $c->email);
    }

    public function test_throws_on_missing_fullName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookContact::fromArray(['email' => 'alice@example.com']);
    }

    public function test_throws_on_missing_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookContact::fromArray(['fullName' => 'Alice Smith']);
    }

    public function test_throws_on_non_string_fullName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookContact::fromArray(['fullName' => false, 'email' => 'a@b.c']);
    }
}
