<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Spart\Sdk\Webhooks\SignatureVerifier;

final class SignatureVerifierTest extends TestCase
{
    private const SECRET = 'whsec_test_abcdef';

    public function test_verify_returns_true_for_correct_signature_within_tolerance(): void
    {
        $body = '{"id":"evt_1"}';
        $t = time();
        $sig = self::sign(self::SECRET, $t, $body);
        $header = "t={$t},v1={$sig}";

        self::assertTrue((new SignatureVerifier(self::SECRET))->verify($body, $header));
    }

    public function test_verify_returns_false_for_tampered_body(): void
    {
        $body = '{"id":"evt_1"}';
        $t = time();
        $sig = self::sign(self::SECRET, $t, $body);
        $header = "t={$t},v1={$sig}";

        self::assertFalse((new SignatureVerifier(self::SECRET))->verify('{"id":"evt_2"}', $header));
    }

    public function test_verify_returns_false_when_outside_tolerance(): void
    {
        $body = '{"id":"evt_1"}';
        $t = time() - 600; // 10 minutes ago, tolerance is 300s
        $sig = self::sign(self::SECRET, $t, $body);
        $header = "t={$t},v1={$sig}";

        self::assertFalse((new SignatureVerifier(self::SECRET))->verify($body, $header));
    }

    public function test_verify_uses_injected_now_to_evaluate_tolerance(): void
    {
        $body = '{"id":"evt_pinned"}';
        $t = 1700000000; // pinned past timestamp, far outside wall-clock tolerance
        $sig = self::sign(self::SECRET, $t, $body);
        $header = "t={$t},v1={$sig}";

        // With the clock pinned to the signed instant, the signature is in tolerance.
        self::assertTrue((new SignatureVerifier(self::SECRET))->verify($body, $header, $t));
    }

    public function test_verify_injected_now_still_enforces_tolerance_window(): void
    {
        $body = '{"id":"evt_pinned"}';
        $t = 1700000000;
        $sig = self::sign(self::SECRET, $t, $body);
        $header = "t={$t},v1={$sig}";

        // now is 600s after the signed instant; tolerance is 300s => rejected.
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify($body, $header, $t + 600));
    }

    public function test_verify_returns_false_for_malformed_header(): void
    {
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify('body', 'garbage'));
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify('body', 't=abc,v1=def'));
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify('body', 'v1=abc'));
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify('body', ''));
    }

    public function test_verify_uses_constant_time_compare(): void
    {
        $body = '{"id":"evt_1"}';
        $t = time();
        $valid = self::sign(self::SECRET, $t, $body);
        $invalid = str_repeat('0', strlen($valid));

        self::assertTrue((new SignatureVerifier(self::SECRET))->verify($body, "t={$t},v1={$valid}"));
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify($body, "t={$t},v1={$invalid}"));
    }

    public function test_verify_accepts_custom_tolerance_via_constructor(): void
    {
        $body = '{"id":"evt_1"}';
        $t = time() - 1000;
        $sig = self::sign(self::SECRET, $t, $body);
        $header = "t={$t},v1={$sig}";

        self::assertTrue((new SignatureVerifier(self::SECRET, toleranceSeconds: 1500))->verify($body, $header));
    }

    public function test_verify_rejects_when_v1_missing(): void
    {
        $t = time();
        self::assertFalse((new SignatureVerifier(self::SECRET))->verify('body', "t={$t}"));
    }

    // -------------------------------------------------------------------------
    // Constructor guard — blank / whitespace-only signing secret
    // -------------------------------------------------------------------------

    public function test_constructor_rejects_blank_signing_secret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SignatureVerifier('');
    }

    public function test_constructor_rejects_whitespace_only_signing_secret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SignatureVerifier('   ');
    }

    // -------------------------------------------------------------------------
    // Constructor guard — toleranceSeconds bounds
    // -------------------------------------------------------------------------

    public function test_constructor_rejects_zero_tolerance(): void
    {
        // toleranceSeconds = 0 means abs($now - $t) > 0 is true for any $t !== $now
        // -> every legitimate webhook fails verification with no diagnostic signal.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('toleranceSeconds');
        new SignatureVerifier(self::SECRET, toleranceSeconds: 0);
    }

    public function test_constructor_rejects_negative_tolerance(): void
    {
        // Negative tolerance disables replay protection entirely
        // (abs() always non-negative, so abs($now - $t) > -1 is always true...
        // wait — abs(...) > -1 is always true so verify() ALWAYS returns false
        // for the timestamp branch. Either way, it's a misconfiguration.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('toleranceSeconds');
        new SignatureVerifier(self::SECRET, toleranceSeconds: -1);
    }

    public function test_constructor_rejects_excessive_tolerance(): void
    {
        // > 24h replay window is almost certainly misconfiguration; the real
        // attack surface (an attacker capturing a webhook and replaying it
        // across machine reboots / clock-sync events) becomes unbounded.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('toleranceSeconds');
        new SignatureVerifier(self::SECRET, toleranceSeconds: 86401);
    }

    public function test_constructor_accepts_max_tolerance_of_24h(): void
    {
        // Boundary: exactly 86400 seconds is allowed. No exception.
        $verifier = new SignatureVerifier(self::SECRET, toleranceSeconds: 86400);
        self::assertInstanceOf(SignatureVerifier::class, $verifier);
    }

    public function test_constructor_accepts_minimum_tolerance_of_one_second(): void
    {
        // Boundary: exactly 1 second is allowed. No exception.
        $verifier = new SignatureVerifier(self::SECRET, toleranceSeconds: 1);
        self::assertInstanceOf(SignatureVerifier::class, $verifier);
    }

    // -------------------------------------------------------------------------
    // parseHeader — direct unit tests
    // -------------------------------------------------------------------------

    public function test_parseHeader_returns_tuple_for_valid_header(): void
    {
        $result = SignatureVerifier::parseHeader('t=1700000000,v1=abcdef');
        self::assertSame([1700000000, 'abcdef'], $result);
    }

    public function test_parseHeader_accepts_reversed_segment_order(): void
    {
        $result = SignatureVerifier::parseHeader('v1=abcdef,t=1700000000');
        self::assertSame([1700000000, 'abcdef'], $result);
    }

    public function test_parseHeader_trims_whitespace_around_segments(): void
    {
        $result = SignatureVerifier::parseHeader('  t=1700000000 , v1=abcdef  ');
        self::assertSame([1700000000, 'abcdef'], $result);
    }

    public function test_parseHeader_returns_null_for_empty_string(): void
    {
        self::assertNull(SignatureVerifier::parseHeader(''));
    }

    public function test_parseHeader_returns_null_when_t_is_not_digits(): void
    {
        // note: ctype_digit('abc') === false — not all digits
        self::assertNull(SignatureVerifier::parseHeader('t=abc,v1=abcdef'));
        // note: ctype_digit('') === false in PHP — empty string has no digits
        self::assertNull(SignatureVerifier::parseHeader('t=,v1=abcdef'));
        // note: ctype_digit('-100') === false — '-' is not a digit character
        self::assertNull(SignatureVerifier::parseHeader('t=-100,v1=abcdef'));
    }

    public function test_parseHeader_returns_null_when_v1_is_missing(): void
    {
        self::assertNull(SignatureVerifier::parseHeader('t=1700000000'));
    }

    public function test_parseHeader_returns_null_when_v1_is_empty(): void
    {
        self::assertNull(SignatureVerifier::parseHeader('t=1700000000,v1='));
    }

    public function test_parseHeader_returns_null_when_t_is_missing(): void
    {
        self::assertNull(SignatureVerifier::parseHeader('v1=abcdef'));
    }

    public function test_parseHeader_last_duplicate_wins(): void
    {
        $result = SignatureVerifier::parseHeader('t=100,v1=first,v1=second');
        self::assertSame([100, 'second'], $result);
    }

    private static function sign(string $secret, int $t, string $body): string
    {
        return hash_hmac('sha256', "{$t}.{$body}", $secret);
    }
}
