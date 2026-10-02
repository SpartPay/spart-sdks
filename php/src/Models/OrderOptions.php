<?php

declare(strict_types=1);

namespace Spart\Sdk\Models;

/**
 * Per-intent ordering options forwarded to the Spart server.
 *
 * `maxDuration` is required because the server-side validator runs whenever
 * the `options` object is present and rejects `MaxDurationTicks < MinDuration`.
 * Sending `{returnUri: ..., cancelUri: ...}` without an explicit duration
 * would therefore fail validation. Construct without `OrderOptions` at all
 * if you want the server to apply its own defaults.
 *
 * `intentDuration` (optional) overrides how long the intent stays open before
 * it expires. The server applies its default (15 minutes) when omitted and
 * rejects values under 1 minute.
 *
 * `returnUri` and `cancelUri` (when provided) must be absolute http/https URIs.
 *
 * @final
 */
final class OrderOptions
{
    public function __construct(
        public readonly \DateInterval $maxDuration,
        public readonly ?string $returnUri = null,
        public readonly ?string $cancelUri = null,
        public readonly ?\DateInterval $intentDuration = null,
    ) {
        if ($this->returnUri !== null && !self::isAbsoluteHttpUri($this->returnUri)) {
            throw new \InvalidArgumentException(
                'OrderOptions::returnUri must be an absolute http or https URI.'
            );
        }
        if ($this->cancelUri !== null && !self::isAbsoluteHttpUri($this->cancelUri)) {
            throw new \InvalidArgumentException(
                'OrderOptions::cancelUri must be an absolute http or https URI.'
            );
        }
    }

    /**
     * Convert {@see $maxDuration} into .NET ticks (1 tick = 100ns).
     *
     * **Calendar intervals are resolved against the Unix epoch (1970-01-01 UTC).**
     * Fixed durations (e.g. `P1D`, `PT6H`) are exact. Calendar-relative components
     * resolve as: `P1M` = 31 days, `P3M` = 90 days, `P1Y` = 365 days. If you need
     * exact fixed-length durations, prefer day/hour/minute/second components.
     */
    public function maxDurationAsTicks(): int
    {
        return self::toTicks($this->maxDuration);
    }

    /**
     * Convert {@see $intentDuration} into .NET ticks, or null when unset.
     * Same epoch-based resolution as {@see maxDurationAsTicks()}.
     */
    public function intentDurationAsTicks(): ?int
    {
        return $this->intentDuration === null ? null : self::toTicks($this->intentDuration);
    }

    private static function toTicks(\DateInterval $interval): int
    {
        $now = new \DateTimeImmutable('@0');
        $end = $now->add($interval);
        $totalSeconds = $end->getTimestamp() - $now->getTimestamp();
        return $totalSeconds * 10_000_000;
    }

    private static function isAbsoluteHttpUri(string $uri): bool
    {
        $parts = parse_url($uri);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && $parts['host'] !== '';
    }
}
