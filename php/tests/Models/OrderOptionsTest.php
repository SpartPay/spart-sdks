<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Models\OrderOptions;

final class OrderOptionsTest extends TestCase
{
    public function test_constructs_with_max_duration_only(): void
    {
        $o = new OrderOptions(maxDuration: new \DateInterval('P1D'));
        self::assertNull($o->returnUri);
        self::assertNull($o->cancelUri);
    }

    public function test_constructs_with_max_duration_and_uris(): void
    {
        $o = new OrderOptions(
            maxDuration: new \DateInterval('P1D'),
            returnUri: 'https://shop.example/return',
            cancelUri: 'https://shop.example/cancel',
        );
        self::assertSame('https://shop.example/return', $o->returnUri);
        self::assertSame('https://shop.example/cancel', $o->cancelUri);
    }

    public function test_max_duration_one_day_in_ticks(): void
    {
        $o = new OrderOptions(maxDuration: new \DateInterval('P1D'));
        // 1 day = 86_400 seconds. .NET tick = 100ns, so 1s = 10_000_000 ticks.
        self::assertSame(864_000_000_000, $o->maxDurationAsTicks());
    }

    public function test_max_duration_six_hours_in_ticks(): void
    {
        $o = new OrderOptions(maxDuration: new \DateInterval('PT6H'));
        self::assertSame(216_000_000_000, $o->maxDurationAsTicks());
    }

    public function test_rejects_relative_return_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OrderOptions(
            maxDuration: new \DateInterval('P1D'),
            returnUri: '/return',
        );
    }

    public function test_rejects_relative_cancel_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OrderOptions(
            maxDuration: new \DateInterval('P1D'),
            cancelUri: '/cancel',
        );
    }

    public function test_rejects_non_http_return_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OrderOptions(
            maxDuration: new \DateInterval('P1D'),
            returnUri: 'javascript:alert(1)',
        );
    }
}
