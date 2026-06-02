<?php

declare(strict_types=1);

namespace Spart\Sdk\Models;

/**
 * Pure descriptive line item — the server treats line items as metadata only
 * and does NOT reconcile their (non-existent) prices against the order total.
 *
 * @final
 */
final class LineItem
{
    public function __construct(
        public readonly string $name,
        public readonly int $quantity,
        public readonly ?string $description = null,
        public readonly ?string $imageUri = null,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('LineItem name must not be blank.');
        }
        if ($this->quantity < 1) {
            throw new \InvalidArgumentException('LineItem quantity must be >= 1.');
        }
        if ($this->imageUri !== null && !self::isAbsoluteHttpUri($this->imageUri)) {
            throw new \InvalidArgumentException(
                'LineItem imageUri must be an absolute http or https URI.'
            );
        }
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
