<?php

declare(strict_types=1);

namespace Spart\Sdk\Dtos;

use Spart\Sdk\Models\Contact;
use Spart\Sdk\Models\LineItem;
use Spart\Sdk\Models\Money;
use Spart\Sdk\Models\OrderOptions;

/**
 * Request body for `POST /api/intents`.
 *
 * Field names and shape match the server's `CreateIntentDto` exactly,
 * including the contact field being named `sparter` (NOT `contact`)
 * and the absence of any top-level `currency` field — currency travels
 * with the `total` Money. Per-line-item prices do not exist on this
 * endpoint: line items are pure descriptive metadata and the server
 * does NOT reconcile them against `total`.
 *
 * @final
 */
final class CreateIntentRequest
{
    /**
     * @param list<LineItem> $lineItems
     */
    public function __construct(
        public readonly Money $total,
        public readonly array $lineItems,
        public readonly Contact $sparter,
        public readonly ?string $sessionId = null,
        public readonly ?OrderOptions $options = null,
    ) {
        if ($this->lineItems === []) {
            throw new \InvalidArgumentException('CreateIntentRequest::lineItems must not be empty.');
        }
        foreach ($this->lineItems as $li) {
            if (!$li instanceof LineItem) {
                throw new \InvalidArgumentException(
                    'CreateIntentRequest::lineItems must contain only LineItem instances.'
                );
            }
        }
        if ($this->sessionId !== null) {
            if (trim($this->sessionId) === '') {
                throw new \InvalidArgumentException(
                    'CreateIntentRequest::sessionId must not be blank when provided.'
                );
            }
            if (strlen($this->sessionId) > 64) {
                throw new \InvalidArgumentException(
                    'CreateIntentRequest::sessionId must be at most 64 characters (server enforces MaxLength 64).'
                );
            }
        }
        if (!self::isPositiveDecimalLexeme($this->total->value)) {
            throw new \InvalidArgumentException(
                'CreateIntentRequest::total.value must be > 0 ' .
                '(server\'s MoneyDtoValidator rejects zero/negative totals).'
            );
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $body = [
            'total' => $this->total,
            'lineItems' => array_map(
                static fn(LineItem $li) => array_filter([
                    'name' => $li->name,
                    'quantity' => $li->quantity,
                    'description' => $li->description,
                    'imageUri' => $li->imageUri,
                ], static fn($v) => $v !== null),
                $this->lineItems,
            ),
            'sparter' => array_filter([
                'email' => $this->sparter->email,
                'firstName' => $this->sparter->firstName,
                'lastName' => $this->sparter->lastName,
            ], static fn($v) => $v !== null),
        ];

        if ($this->sessionId !== null) {
            $body['sessionId'] = $this->sessionId;
        }

        if ($this->options !== null) {
            // maxDurationTicks is ALWAYS emitted when options is present:
            // the server's OrderOptions validator runs unconditionally on the
            // sub-object and rejects MaxDurationTicks < OrderOptions.MinDuration.
            $opts = ['maxDurationTicks' => $this->options->maxDurationAsTicks()];
            if ($this->options->returnUri !== null) {
                $opts['returnUri'] = $this->options->returnUri;
            }
            if ($this->options->cancelUri !== null) {
                $opts['cancelUri'] = $this->options->cancelUri;
            }
            $body['options'] = $opts;
        }

        return $body;
    }

    private static function isPositiveDecimalLexeme(string $lexeme): bool
    {
        // Already validated by Money as JSON-number-safe: -?(0|[1-9]\d*)(\.\d+)?
        if ($lexeme === '' || $lexeme[0] === '-') {
            return false;
        }
        // Reject "0", "0.0", "0.000".
        return preg_match('/^0+(\.0+)?$/', $lexeme) !== 1;
    }
}
