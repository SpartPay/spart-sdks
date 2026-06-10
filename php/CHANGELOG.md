# Changelog

All notable changes to `spart/sdk` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Webhook `order.created` event (`EventType::OrderCreated`), routed to the
  order sub-envelope.
- `OrderEnvelopeData::$paymentParts`: an optional, possibly-empty list of
  `WebhookPaymentPart` describing the payees, their charge breakdown
  (net/total/fees) and per-part status.
- `WebhookPaymentPart` and `WebhookCharge` models, plus an `optionalList()`
  envelope helper.

### Changed

- `EventType` gained a new case (`OrderCreated`). **Consumers that `match()`
  exhaustively over `EventType` must add an arm for the new case** (or a
  default arm) — this is a behavioural change for exhaustive matches even
  though it is source-compatible for non-exhaustive ones. Treat the next
  release carrying this change as a **minor** version bump.

### Notes

- Payee identity (`payee.fullName` / `payee.email`) is masked by the backend
  before transmission. The SDK preserves whatever the server sends verbatim and
  performs no masking of its own; downstream consumers should still treat these
  fields as potentially sensitive and avoid persisting raw values.
