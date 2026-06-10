<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks\Models;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\Models\WebhookCharge;
use Spart\Sdk\Webhooks\Models\WebhookContact;
use Spart\Sdk\Webhooks\Models\WebhookPaymentPart;

/**
 * Locks down the wire shape of a single payment part (payee).
 */
final class WebhookPaymentPartTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function validRow(): array
    {
        return [
            'id'          => '11111111-1111-1111-1111-111111111111',
            'amount'      => 100,
            'amountType'  => 'Percent',
            'status'      => 'captured',
            'isSparter'   => true,
            'payee'       => ['fullName' => 'Alice S', 'email' => 'a****e@e******le.com'],
            'payeeCharge' => [
                'net'   => ['currency' => 'EUR', 'amount' => 195.00],
                'total' => ['currency' => 'EUR', 'amount' => 199.99],
                'fees'  => ['platform' => 4.99],
            ],
            'authorizedAt' => '2026-06-09T00:15:16+00:00',
            'capturedAt'   => '2026-06-09T00:16:00+00:00',
            'releasedAt'   => null,
        ];
    }

    public function test_happy_path(): void
    {
        $p = WebhookPaymentPart::fromArray(self::validRow());

        self::assertSame('11111111-1111-1111-1111-111111111111', $p->id);
        self::assertSame(100.0, $p->amount);
        self::assertSame('Percent', $p->amountType);
        self::assertSame('captured', $p->status);
        self::assertTrue($p->isSparter);
        self::assertInstanceOf(WebhookContact::class, $p->payee);
        self::assertSame('Alice S', $p->payee->fullName);
        self::assertInstanceOf(WebhookCharge::class, $p->payeeCharge);
        self::assertSame(199.99, $p->payeeCharge->total->amount);
        self::assertSame('2026-06-09T00:15:16+00:00', $p->authorizedAt);
        self::assertSame('2026-06-09T00:16:00+00:00', $p->capturedAt);
        self::assertNull($p->releasedAt);
    }

    public function test_amount_type_preserves_pascal_case(): void
    {
        // amountType is intentionally PascalCase on the wire; do not normalize.
        self::assertSame('Percent', WebhookPaymentPart::fromArray(self::validRow())->amountType);
    }

    public function test_optional_timestamps_default_to_null_when_absent(): void
    {
        $row = self::validRow();
        unset($row['authorizedAt'], $row['capturedAt'], $row['releasedAt']);
        $p = WebhookPaymentPart::fromArray($row);
        self::assertNull($p->authorizedAt);
        self::assertNull($p->capturedAt);
        self::assertNull($p->releasedAt);
    }

    public function test_is_sparter_false(): void
    {
        $row = self::validRow();
        $row['isSparter'] = false;
        self::assertFalse(WebhookPaymentPart::fromArray($row)->isSparter);
    }

    public function test_throws_on_missing_id(): void
    {
        $row = self::validRow();
        unset($row['id']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookPaymentPart::fromArray($row);
    }

    public function test_throws_on_missing_amount(): void
    {
        $row = self::validRow();
        unset($row['amount']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookPaymentPart::fromArray($row);
    }

    public function test_throws_on_non_bool_is_sparter(): void
    {
        // Strict: 0/1 int must not be silently coerced.
        $row = self::validRow();
        $row['isSparter'] = 1;
        $this->expectException(\InvalidArgumentException::class);
        WebhookPaymentPart::fromArray($row);
    }

    public function test_throws_on_missing_payee(): void
    {
        $row = self::validRow();
        unset($row['payee']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookPaymentPart::fromArray($row);
    }

    public function test_throws_on_missing_payee_charge(): void
    {
        $row = self::validRow();
        unset($row['payeeCharge']);
        $this->expectException(\InvalidArgumentException::class);
        WebhookPaymentPart::fromArray($row);
    }
}
