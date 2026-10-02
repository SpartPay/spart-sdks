# Changelog

All notable changes to `spart/sdk` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `OrderOptions::$intentDuration`: optional `\DateInterval` overriding the
  intent TTL (server default 15 minutes, minimum 1 minute). Sent as
  `options.intentDurationTicks` only when set.
- Webhook `intent.expired` event (`EventType::IntentExpired`), routed to the
  intent sub-envelope.
- `IntentEnvelopeData::$expirationDate` and `$expiredAt`: nullable ISO 8601
  strings for when the intent expires and when it expired.
- `CreateIntentRequest::$desiredLanguage`: optional customer UI language
  (ISO 639-1 like `fr`, or a locale like `fr_FR`) sent to `POST /api/intents`
  as `desiredLanguage`. Trimmed on construction; blank becomes `null`; capped
  at 35 characters. Emitted only when non-null. Backward compatible — the new
  constructor parameter is optional and last.
- Webhook `order.created` event (`EventType::OrderCreated`), routed to the
  order sub-envelope.
- Webhook `order.payment_part_released` event (`EventType::PaymentPartReleased`), routed to the `payment` sub-envelope.
- `OrderEnvelopeData::$paymentParts`: an optional, possibly-empty list of
  `WebhookPaymentPart` describing the payees, their charge breakdown
  (net/total/fees) and per-part status.
- `PaymentPartReleasedEnvelopeData` DTO (`orderShortId`, `sessionId`, `paymentPartId`, `amountReleased`, `payee`, `releasedAt`).
- `WebhookPaymentPart` and `WebhookCharge` models, plus an `optionalList()`
  envelope helper.

### Changed

- **BC:** `IntentEnvelopeData::$expiresOn` is renamed to `$orderExpiresOn`
  (wire key `orderExpiresOn`). It is the order expiry, not the intent expiry.
- `EventType` now has 9 cases. **BC:** Any consumer performing an exhaustive
  `match (EventType)` without a `default` arm must add branches for
  `EventType::PaymentPartReleased` and `EventType::IntentExpired` or PHP will
  throw `UnhandledMatchError`.

### Notes

- Payee identity (`payee.fullName` / `payee.email`) is masked by the backend
  before transmission. The SDK preserves whatever the server sends verbatim and
  performs no masking of its own; downstream consumers should still treat these
  fields as potentially sensitive and avoid persisting raw values.
