# Changelog

All notable changes to `spart/sdk` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

- `EventType` now has 8 cases. **BC:** Any consumer performing an exhaustive
  `match (EventType)` without a `default` arm must add a branch for
  `EventType::PaymentPartReleased` or PHP will throw `UnhandledMatchError`.

### Notes

- Payee identity (`payee.fullName` / `payee.email`) is masked by the backend
  before transmission. The SDK preserves whatever the server sends verbatim and
  performs no masking of its own; downstream consumers should still treat these
  fields as potentially sensitive and avoid persisting raw values.
