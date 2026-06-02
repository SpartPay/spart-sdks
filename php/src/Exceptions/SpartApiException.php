<?php

declare(strict_types=1);

namespace Spart\Sdk\Exceptions;

class SpartApiException extends SpartException
{
    /**
     * @param string|null $errorCode machine-readable discriminator from the
     *     failure envelope's `error.code` field (issue #197). Stable, snake_case
     *     identifier (e.g. `intent.session_conflict`); null when the server
     *     response is bodyless (some 401/429/5xx) or pre-#197.
     * @param list<string> $errorDetails human-readable messages flattened from
     *     the failure envelope's `error.additionalData` field, if any.
     * @param array<string,mixed>|null $rawBody decoded JSON body for diagnostics.
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $errorCode = null,
        public readonly array $errorDetails = [],
        public readonly ?array $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
