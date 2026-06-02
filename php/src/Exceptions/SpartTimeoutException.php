<?php

declare(strict_types=1);

namespace Spart\Sdk\Exceptions;

/**
 * Raised when an HTTP request times out at the transport layer
 * (cURL CURLE_OPERATION_TIMEDOUT). Distinct subclass of
 * {@see SpartTransportException} so {@see \Spart\Sdk\Http\RetryingHttpClient}
 * can target retryable timeouts without re-attempting non-timeout
 * transport errors (DNS failures, TLS handshake failures, etc.).
 */
class SpartTimeoutException extends SpartTransportException
{
}
