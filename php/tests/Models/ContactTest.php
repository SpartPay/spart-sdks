<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Models\Contact;

final class ContactTest extends TestCase
{
    public function test_constructs_with_email_only(): void
    {
        $c = new Contact(email: 'a@b.com');
        self::assertSame('a@b.com', $c->email);
        self::assertNull($c->firstName);
        self::assertNull($c->lastName);
    }

    public function test_constructs_with_full_name(): void
    {
        $c = new Contact(email: 'a@b.com', firstName: 'Jane', lastName: 'Doe');
        self::assertSame('Jane', $c->firstName);
        self::assertSame('Doe', $c->lastName);
    }

    public function test_rejects_blank_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Contact(email: '');
    }

    public function test_rejects_whitespace_only_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Contact(email: '   ');
    }
}
