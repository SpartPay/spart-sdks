<?php

declare(strict_types=1);

namespace Spart\Sdk\Webhooks;

/**
 * The closed set of webhook event types the Spart server emits.
 *
 * Adding a case here without a matching server change is a bug — see
 * EventTypeTest for the drift-prevention asserts.
 *
 * Forward-compatibility: when a webhook arrives with a `type` value
 * not in this enum, `Event::knownType` is `null` and `Event::data` is
 * `null` (but `Event::type` still carries the raw string). Consumers
 * should treat unknown types as "log and ignore", not as errors.
 */
enum EventType: string
{
    case IntentCreated     = 'intent.created';
    case PaymentAuthorized = 'payment.authorized';
    case OrderCreated      = 'order.created';
    case OrderCompleted    = 'order.completed';
    case OrderCanceled     = 'order.canceled';
    case OrderExpired      = 'order.expired';
    case WebhookTest       = 'webhook.test';
}
