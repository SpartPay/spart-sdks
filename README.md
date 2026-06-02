# Spart SDKs

Official, open-source Spart client code. One repository, one folder per language.

| Folder    | Package manager | Package         | Status                         |
|-----------|-----------------|-----------------|--------------------------------|
| `php/`    | Composer        | `spart/sdk`     | Full SDK                       |
| `node/`   | npm             | `@spartpay/sdk` | Webhook signature verification |
| `python/` | PyPI            | `spart-sdk`     | Webhook signature verification |
| `dotnet/` | NuGet           | `Spart.Sdk`     | Webhook signature verification |

## Webhook signature verification

Every library exposes the same shape:

- `new SignatureVerifier(secret, toleranceSeconds = 300)`
- `verify(rawBody, signatureHeader, now?) -> bool`

It verifies the `X-Spart-Signature: t=<unix-seconds>,v1=<lowercase-hex-sha256>`
header: HMAC-SHA256 over `"{t}." + rawBody` (raw bytes), constant-time compared,
within a configurable timestamp tolerance. See each folder's README for install
and usage.

## Shared fixtures

`fixtures.json` pins a set of signed webhooks (secret, timestamp, raw body,
expected `v1`). All four test suites verify against it, guaranteeing
byte-identical signatures across languages. Regenerate with
`node tools/generate-fixtures.mjs`.
