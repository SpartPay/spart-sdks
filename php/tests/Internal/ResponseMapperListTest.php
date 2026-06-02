<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Exceptions\SpartApiException;
use Spart\Sdk\Exceptions\SpartAuthException;
use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Http\HttpResponse;
use Spart\Sdk\Internal\ResponseMapper;

/**
 * Tests {@see ResponseMapper::toIntentList}. Failure mapping is
 * shared with the other endpoints via `throwForFailureStatus` so
 * we cover the list-specific success path plus a representative
 * sample of failures (401, 5xx already covered by sibling tests).
 */
final class ResponseMapperListTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function intentRow(string $shortId): array
    {
        return [
            'shortId' => $shortId,
            'total' => ['value' => '100.00', 'currency' => 'CAD'],
            'lineItems' => [['name' => 'Widget', 'quantity' => 1]],
            'createdAt' => '2026-05-11T12:34:56+00:00',
            'orderId' => null,
            'sessionId' => 'sess_xyz',
            'isCompleted' => false,
            'links' => [['name' => 'Checkout', 'url' => "https://example.test/c/{$shortId}"]],
        ];
    }

    public function test_200_unwraps_envelope_and_returns_intent_list(): void
    {
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => [
                'items' => [self::intentRow('a1'), self::intentRow('a2')],
                'page' => 1,
                'requestedPageSize' => 10,
                'currentPageSize' => 2,
                'totalPagesCount' => 1,
                'totalItemsCount' => 2,
            ],
            'error' => null,
        ]);
        $list = ResponseMapper::toIntentList(new HttpResponse(200, [], $body));
        self::assertCount(2, $list->items);
        self::assertSame('a1', $list->items[0]->shortId);
        self::assertSame(2, $list->totalItemsCount);
    }

    public function test_200_with_missing_value_throws_spart_api_exception(): void
    {
        $body = (string) json_encode(['isSuccessful' => true]);
        try {
            ResponseMapper::toIntentList(new HttpResponse(200, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertStringContainsString('missing "value"', $e->getMessage());
        }
    }

    public function test_200_with_malformed_value_throws_with_underlying_cause(): void
    {
        $bad = self::intentRow('a1');
        unset($bad['shortId']);
        $body = (string) json_encode([
            'isSuccessful' => true,
            'value' => [
                'items' => [$bad],
                'page' => 1,
                'requestedPageSize' => 10,
                'currentPageSize' => 1,
                'totalPagesCount' => 1,
                'totalItemsCount' => 1,
            ],
            'error' => null,
        ]);
        try {
            ResponseMapper::toIntentList(new HttpResponse(200, [], $body));
            $this->fail('expected SpartApiException');
        } catch (SpartApiException $e) {
            self::assertStringContainsString('malformed IntentList body', $e->getMessage());
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function test_200_with_non_json_body_throws_transport_exception(): void
    {
        $this->expectException(SpartTransportException::class);
        ResponseMapper::toIntentList(new HttpResponse(200, [], '<html>oops</html>'));
    }

    public function test_401_throws_auth_exception(): void
    {
        $this->expectException(SpartAuthException::class);
        ResponseMapper::toIntentList(new HttpResponse(401, [], ''));
    }
}
