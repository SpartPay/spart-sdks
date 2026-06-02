<?php

declare(strict_types=1);

namespace Spart\Sdk\Exceptions;

final class SpartRateLimitException extends SpartApiException
{
    /**
     * @param list<string> $errorDetails
     * @param array<string,mixed>|null $rawBody
     */
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?string $errorCode = null,
        array $errorDetails = [],
        ?array $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            statusCode: 429,
            errorCode: $errorCode,
            errorDetails: $errorDetails,
            rawBody: $rawBody,
            previous: $previous,
        );
    }
}
