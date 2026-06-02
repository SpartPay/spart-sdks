<?php

declare(strict_types=1);

namespace Spart\Sdk\Dtos;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * Snapshot of a merchant's current ability to start payment intents.
 *
 * When `eligible` is true, `reasons` is empty. When `eligible` is false,
 * `reasons` holds one or more {@see EligibilityReason} describing the
 * blocking conditions (e.g. Stripe not connected, onboarding incomplete).
 *
 * Constructed via the static {@see self::fromArray()} factory from a
 * JSON-decoded `value` object inside the standard server Result envelope.
 *
 * @final
 */
final class Eligibility
{
    /** @param list<EligibilityReason> $reasons */
    private function __construct(
        public readonly bool $eligible,
        public readonly array $reasons,
    ) {
    }

    /**
     * @param array<string,mixed> $row The decoded `value` object — NOT the full envelope.
     * @throws \InvalidArgumentException when shape is malformed.
     */
    public static function fromArray(array $row): self
    {
        $eligible = EnvelopeFieldHelper::requireBool($row, 'eligible', 'Eligibility');
        $rawReasons = EnvelopeFieldHelper::requireList($row, 'reasons', 'Eligibility');

        $reasons = [];
        foreach ($rawReasons as $i => $raw) {
            if (!is_array($raw)) {
                throw new \InvalidArgumentException("Eligibility: reasons[{$i}] is not an object");
            }
            /** @var array<string,mixed> $raw */
            $reasons[] = EligibilityReason::fromArray($raw);
        }

        return new self(eligible: $eligible, reasons: $reasons);
    }
}
