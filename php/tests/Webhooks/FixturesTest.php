<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\SignatureVerifier;

final class FixturesTest extends TestCase
{
    /** @return array{version:int,secret:string,cases:list<array<string,mixed>>} */
    private static function fixture(): array
    {
        $raw = file_get_contents(__DIR__ . '/../../../fixtures.json');
        self::assertNotFalse($raw, 'fixtures.json must exist at the repo root');
        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string,array{0:array<string,mixed>}> */
    public static function caseProvider(): iterable
    {
        foreach (self::fixture()['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @param array<string,mixed> $case
     * @dataProvider caseProvider
     */
    public function test_shared_cross_language_fixture(array $case): void
    {
        $secret = self::fixture()['secret'];
        $body = isset($case['rawBodyBase64'])
            ? base64_decode($case['rawBodyBase64'], true)
            : $case['rawBodyUtf8'];
        self::assertIsString($body, 'fixture body must decode to a string');

        $verifier = new SignatureVerifier($secret);

        self::assertSame(
            $case['expected'],
            $verifier->verify($body, $case['signatureHeader'], now: $case['now']),
            "case: {$case['name']}"
        );
    }
}
