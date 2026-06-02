<?php

declare(strict_types=1);

namespace Spart\Sdk\Dtos;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/**
 * One reason a merchant is currently not eligible to start payment intents.
 *
 * Mirrors the server's {@code ErrorDetails} record (code, message,
 * optional free-form additionalData). Constructed via the static
 * {@see self::fromArray()} factory from a JSON-decoded body.
 *
 * @final
 */
final class EligibilityReason
{
    /**
     * @param mixed $additionalData Server-defined free-form payload.
     *                              Currently always `null` for the two known
     *                              reasons but kept open to track the server
     *                              contract.
     */
    private function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly mixed $additionalData,
    ) {
    }

    /**
     * @param array<string,mixed> $row
     * @throws \InvalidArgumentException when `code` or `message` are missing or non-string.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            code: EnvelopeFieldHelper::requireString($row, 'code', 'EligibilityReason'),
            message: EnvelopeFieldHelper::requireString($row, 'message', 'EligibilityReason'),
            additionalData: $row['additionalData'] ?? null,
        );
    }
}
