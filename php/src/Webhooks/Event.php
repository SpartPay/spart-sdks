<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

use Spart\Sdk\Internal\EnvelopeFieldHelper;

/** @final */
final class Event
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?EventType $knownType,
        public readonly string $createdAt,
        public readonly string $apiVersion,
        public readonly string $merchantAppId,
        public readonly ?EnvelopeData $data,
        public readonly string $deliveryId,
        public readonly int $attempt,
    ) {
    }

    public static function fromJson(string $rawBody, string $deliveryId, int $attempt): self
    {
        try {
            $body = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Event body is not valid JSON: ' . $e->getMessage(), previous: $e);
        }
        if (!is_array($body)) {
            throw new \InvalidArgumentException('Event body must be a JSON object.');
        }

        $id = EnvelopeFieldHelper::requireString($body, 'id', 'Event');
        $type = EnvelopeFieldHelper::requireString($body, 'type', 'Event');
        $createdAt = EnvelopeFieldHelper::requireString($body, 'createdAt', 'Event');
        $apiVersion = EnvelopeFieldHelper::requireString($body, 'apiVersion', 'Event');
        $merchantAppId = EnvelopeFieldHelper::requireString($body, 'merchantAppId', 'Event');

        $known = EventType::tryFrom($type);
        $data = self::parseData($known, is_array($body['data'] ?? null) ? $body['data'] : []);

        return new self(
            id: $id,
            type: $type,
            knownType: $known,
            createdAt: $createdAt,
            apiVersion: $apiVersion,
            merchantAppId: $merchantAppId,
            data: $data,
            deliveryId: $deliveryId,
            attempt: $attempt,
        );
    }

    /** @param array<string,mixed> $data */
    private static function parseData(?EventType $known, array $data): ?EnvelopeData
    {
        if ($known === null) {
            return null;
        }
        $intent = is_array($data['intent'] ?? null) ? $data['intent'] : null;
        $order = is_array($data['order'] ?? null) ? $data['order'] : null;
        $payment = is_array($data['payment'] ?? null) ? $data['payment'] : null;
        $test = is_array($data['test'] ?? null) ? $data['test'] : null;

        return match ($known) {
            EventType::IntentCreated
                => $intent !== null ? IntentEnvelopeData::fromArray($intent) : null,
            EventType::OrderCreated, EventType::OrderCompleted, EventType::OrderCanceled, EventType::OrderExpired
                => $order !== null ? OrderEnvelopeData::fromArray($order) : null,
            EventType::PaymentAuthorized
                => $payment !== null ? PaymentEnvelopeData::fromArray($payment) : null,
            EventType::PaymentPartReleased
                => $payment !== null ? PaymentPartReleasedEnvelopeData::fromArray($payment) : null,
            EventType::WebhookTest
                => $test !== null ? TestEnvelopeData::fromArray($test) : null,
        };
    }
}
