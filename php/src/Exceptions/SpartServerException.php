<?php

declare(strict_types=1);

namespace Spart\Sdk\Exceptions;

/**
 * Raised for HTTP 5xx responses from the Spart API. Distinct subclass of
 * {@see SpartApiException} so {@see \Spart\Sdk\Http\RetryingHttpClient}
 * can retry server-side failures without re-attempting client-side 4xx
 * errors (validation, auth, idempotency conflicts, etc.).
 */
class SpartServerException extends SpartApiException
{
}
