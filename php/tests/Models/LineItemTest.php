<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Models\LineItem;

final class LineItemTest extends TestCase
{
    public function test_constructs_with_required_fields(): void
    {
        $item = new LineItem(name: 'Socks', quantity: 2);
        self::assertSame('Socks', $item->name);
        self::assertSame(2, $item->quantity);
        self::assertNull($item->description);
        self::assertNull($item->imageUri);
    }

    public function test_constructs_with_optional_metadata(): void
    {
        $item = new LineItem(
            name: 'Socks',
            quantity: 2,
            description: 'Wool socks, black',
            imageUri: 'https://shop.example/socks.png',
        );
        self::assertSame('Wool socks, black', $item->description);
        self::assertSame('https://shop.example/socks.png', $item->imageUri);
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineItem(name: 'X', quantity: 0);
    }

    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineItem(name: 'X', quantity: -1);
    }

    public function test_rejects_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineItem(name: '   ', quantity: 1);
    }

    public function test_rejects_non_absolute_image_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineItem(name: 'X', quantity: 1, imageUri: '/relative/path.png');
    }

    public function test_rejects_non_http_image_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineItem(name: 'X', quantity: 1, imageUri: 'ftp://x.example/img.png');
    }
}
