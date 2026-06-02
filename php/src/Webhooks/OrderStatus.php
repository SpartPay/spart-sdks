<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

/**
 * Wire-encoded order status values emitted in webhook payloads.
 *
 * Consumers should branch on these named constants rather than typing
 * magic strings, so that any silent server-side drift surfaces in
 * OrderStatusTest instead of mid-checkout.
 *
 * The unusual ALL_PAYMENTS_AUTHORIZED wire token "allpaymentsauthorized"
 * (no separator) is NOT a typo — it is the literal lowercased PascalCase
 * enum name emitted by the server.
 *
 * @final
 */
final class OrderStatus
{
    public const PLACED = 'placed';
    public const ALL_PAYMENTS_AUTHORIZED = 'allpaymentsauthorized';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';
    public const EXPIRED = 'expired';

    /** @var list<string> */
    public const ALL = [
        self::PLACED,
        self::ALL_PAYMENTS_AUTHORIZED,
        self::COMPLETED,
        self::CANCELED,
        self::EXPIRED,
    ];

    public static function isKnown(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    private function __construct()
    {
    }
}
