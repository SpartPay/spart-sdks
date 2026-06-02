# Spart PHP SDK

Official PHP SDK for the [Spart](https://api.spartpay.com) payment-splitting platform.

> ## ⚠️ Verification Status
>
> **Create Intent** is fully wire-validated against the live server: request/response
> shape (`POST /api/intents`, header `x-spart-merchant-api-key`, `Result<T>` envelope
> unwrap, `intentShortId` / `checkoutUrl`), happy path, replay idempotency, the 400
> validation path, and the 401 auth path are all covered by the E2E suite (see
> "Running E2E tests" below).
>
> **Webhook signature scheme** is wire-validated end-to-end: the SDK's
> `SignatureVerifier` and the server's `WebhookSignatureBuilder` agree on
> HMAC-SHA256 over `"{t}.{body}"` with header `t=<unix>,v1=<lowercase-hex>`. This
> is exercised by the `SignatureVerifierTest` self-sign loop in the E2E suite.
>
> **Webhook envelope DTOs** (`IntentEnvelopeData`, `OrderEnvelopeData`,
> `PaymentEnvelopeData`, `TestEnvelopeData`) and the `EventType` enum values are
> still **not** wire-validated against real webhook deliveries. Capturing a
> real-delivery envelope is tracked as a follow-up issue and may surface
> field-name or shape adjustments before the webhook surface is considered stable.

## Installation

Install via Composer:

```bash
composer require spart/sdk
```

**PHP version requirement:** PHP 8.1 or higher.

## Usage

### Create a Client

```php
<?php
use Spart\Sdk\SpartClient;
use Spart\Sdk\SpartClientConfig;

$config = new SpartClientConfig(
    apiKey: 'sk_live_your_api_key_here',
    baseUrl: 'https://api.spart.example'
);
$client = new SpartClient($config);
```

### Create an Intent

```php
<?php
use Spart\Sdk\Dtos\CreateIntentRequest;
use Spart\Sdk\Models\Contact;
use Spart\Sdk\Models\LineItem;
use Spart\Sdk\Models\Money;
use Spart\Sdk\Models\OrderOptions;

$request = new CreateIntentRequest(
    total: Money::fromMinorUnits(11000, decimals: 2, currency: 'EUR'),  // 110.00 EUR
    lineItems: [
        new LineItem(
            name: 'Product A',
            quantity: 1,
            description: 'Optional description',
        ),
        new LineItem(
            name: 'Product B',
            quantity: 2,
            imageUri: 'https://merchant.example/img/b.png',
        ),
    ],
    sparter: new Contact(
        email: 'customer@example.com',
        firstName: 'Jane',
        lastName: 'Doe',
    ),
    // sessionId is OPTIONAL: when supplied, retrying the same payload
    // returns the existing intent (HTTP 200) instead of creating a duplicate.
    sessionId: 'cart_abc123',
    // options is OPTIONAL: when omitted, the server applies its defaults.
    // When provided, maxDuration is REQUIRED.
    options: new OrderOptions(
        maxDuration: new \DateInterval('P1D'),
        returnUri: 'https://merchant.example/checkout/return',
        cancelUri: 'https://merchant.example/checkout/cancel',
    ),
);

$result = $client->intents()->create($request);
echo $result->intentShortId . PHP_EOL;       // Short ID for URLs / follow-up reads
echo $result->checkoutUrl . PHP_EOL;         // Checkout URL to redirect the customer to
echo ($result->wasIdempotentReplay ? 'replay' : 'new') . PHP_EOL;
```

> **Note on line items:** the server treats `lineItems` as descriptive metadata
> and does NOT reconcile them against `total`. There is no per-line-item price.
> The canonical amount the customer is charged is `total` (currency travels with it).

### Verify a Webhook

```php
<?php
use Spart\Sdk\Webhooks\EventType;
use Spart\Sdk\Webhooks\SignatureVerifier;

$verifier = new SignatureVerifier('whsec_your_signing_secret_here');
$event = $verifier->verifyAndParse(
    rawBody: $rawRequestBody,
    headerValue: $_SERVER['HTTP_X_SPART_SIGNATURE'] ?? '',
    deliveryId: $_SERVER['HTTP_X_SPART_DELIVERY_ID'] ?? '',
    attempt: (int) ($_SERVER['HTTP_X_SPART_WEBHOOK_ATTEMPT'] ?? 1),
);

switch ($event->knownType) {
    case EventType::IntentCreated:
        // Handle intent created
        break;
    case EventType::PaymentAuthorized:
        // Handle a payment part being authorized
        break;
    case EventType::OrderCompleted:
        // Handle order completed (all parts authorized)
        break;
    case EventType::OrderCanceled:
        // Handle order canceled
        break;
    case EventType::OrderExpired:
        // Handle order expired (intent timed out before completion)
        break;
    case EventType::WebhookTest:
        // Handle merchant-initiated test ping
        break;
    default:
        // Unknown / forward-compat event type — ignore or log
        break;
}
```

## Public Surface

All classes and interfaces under the `Spart\Sdk` namespace (except those under `Spart\Sdk\Internal\*`) are considered public API and follow semantic versioning.

**Exception:** Classes, interfaces, and functions under the `Spart\Sdk\Internal\*` namespace are **not** part of the public API contract and may change or be removed in any release. Do not depend on internal APIs.

## License

MIT
