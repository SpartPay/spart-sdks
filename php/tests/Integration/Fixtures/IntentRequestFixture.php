<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Integration\Fixtures;

use Spart\Sdk\Dtos\CreateIntentRequest;
use Spart\Sdk\Models\Contact;
use Spart\Sdk\Models\LineItem;
use Spart\Sdk\Models\Money;
use Spart\Sdk\Models\OrderOptions;

/**
 * Builds a {@see CreateIntentRequest} that the Spart API will accept
 * with no further configuration. All currency / amount / contact / option
 * defaults are baked in so individual tests only have to choose a
 * `sessionId` (or accept the random one).
 *
 * Centralised here to avoid the 3-way duplication that previously lived
 * in `CreateIntentTest`, `ReadIntentTest`, and `ListIntentsTest`.
 *
 * @internal — test-only fixture; not part of the SDK contract.
 */
final class IntentRequestFixture
{
    public static function valid(?string $sessionId = null): CreateIntentRequest
    {
        return new CreateIntentRequest(
            total: Money::fromMinorUnits(10000, 2, 'CAD'),
            lineItems: [
                new LineItem('Widget', 1, description: 'Test widget'),
            ],
            sparter: new Contact(
                email: 'ada@example.test',
                firstName: 'Ada',
                lastName: 'Lovelace',
            ),
            sessionId: $sessionId ?? 'sess_' . bin2hex(random_bytes(8)),
            options: new OrderOptions(
                maxDuration: new \DateInterval('PT6H'),
                returnUri: 'https://example.test/return',
                cancelUri: 'https://example.test/cancel',
            ),
        );
    }
}
