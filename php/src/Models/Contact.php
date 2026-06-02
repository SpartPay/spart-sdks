<?php

declare(strict_types=1);

namespace Spart\Sdk\Models;

/** @final */
final class Contact
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {
        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('Contact email must not be blank.');
        }
    }
}
