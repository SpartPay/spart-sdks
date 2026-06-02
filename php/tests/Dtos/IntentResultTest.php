<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Dtos;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Dtos\IntentResult;

final class IntentResultTest extends TestCase
{
    public function test_from_array_parses_canonical_response(): void
    {
        $r = IntentResult::fromArray([
            'intentShortId' => 'abc123',
            'checkoutUrl' => 'https://app.spart/checkout/abc123',
        ], wasReplay: false);
        self::assertSame('abc123', $r->intentShortId);
        self::assertSame('https://app.spart/checkout/abc123', $r->checkoutUrl);
        self::assertFalse($r->wasIdempotentReplay);
    }

    public function test_from_array_marks_replay_when_flag_set(): void
    {
        $r = IntentResult::fromArray([
            'intentShortId' => 'abc',
            'checkoutUrl' => 'https://x/c',
        ], wasReplay: true);
        self::assertTrue($r->wasIdempotentReplay);
    }

    public function test_fromArray_rejects_empty_short_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentResult::fromArray([
            'intentShortId' => '',
            'checkoutUrl' => 'https://x/c',
        ], wasReplay: false);
    }

    public function test_fromArray_rejects_missing_checkout_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentResult::fromArray([
            'intentShortId' => 'abc',
        ], wasReplay: false);
    }

    public function test_fromArray_rejects_non_string_short_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentResult::fromArray([
            'intentShortId' => 42,
            'checkoutUrl' => 'https://x/c',
        ], wasReplay: false);
    }
}
