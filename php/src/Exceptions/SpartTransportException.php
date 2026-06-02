<?php

declare(strict_types=1);

namespace Spart\Sdk\Exceptions;

/**
 * Raised for low-level HTTP transport failures (DNS, TCP, TLS, timeouts,
 * connection resets, etc.). Not `final` so domain-specific subclasses like
 * {@see SpartTimeoutException} can refine it without losing existing
 * `catch (SpartTransportException)` semantics in caller code.
 */
class SpartTransportException extends SpartException
{
}
