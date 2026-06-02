<?php

declare(strict_types=1);

namespace Spart\Sdk\Models;

/**
 * A typed link returned alongside an {@see \Spart\Sdk\Dtos\IntentDetails}.
 * Mirrors the server's `LinkDto` (name + url). The server uses these to
 * advertise contextual URLs such as the checkout page or the completed
 * order's status page; the link `name` indicates which.
 *
 * @final
 */
final class Link
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Link::name must not be blank.');
        }
        if (trim($this->url) === '') {
            throw new \InvalidArgumentException('Link::url must not be blank.');
        }
    }
}
