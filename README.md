# Spart SDKs

Official, open-source Spart client code. One repository, one folder per language.

| Folder    | Package manager | Package         | Status                         | Version | CI |
|-----------|-----------------|-----------------|--------------------------------|---------|----|
| `php/`    | Composer        | `spart/sdk`     | Full SDK                       | —       | [![php CI](https://github.com/SpartPay/spart-sdks/actions/workflows/php.yml/badge.svg)](https://github.com/SpartPay/spart-sdks/actions/workflows/php.yml) |
| `node/`   | npm             | [`@spartpay/sdk`](https://www.npmjs.com/package/@spartpay/sdk) | Webhook signature verification | [![npm version](https://img.shields.io/npm/v/@spartpay/sdk.svg?logo=npm)](https://www.npmjs.com/package/@spartpay/sdk) | [![node CI](https://github.com/SpartPay/spart-sdks/actions/workflows/node.yml/badge.svg)](https://github.com/SpartPay/spart-sdks/actions/workflows/node.yml) |
| `python/` | PyPI            | `spart-sdk`     | Webhook signature verification | —       | [![python CI](https://github.com/SpartPay/spart-sdks/actions/workflows/python.yml/badge.svg)](https://github.com/SpartPay/spart-sdks/actions/workflows/python.yml) |
| `dotnet/` | NuGet           | [`Spart.Sdk`](https://www.nuget.org/packages/Spart.Sdk) | Webhook signature verification | [![NuGet version](https://img.shields.io/nuget/v/Spart.Sdk.svg?logo=nuget)](https://www.nuget.org/packages/Spart.Sdk) | [![dotnet CI](https://github.com/SpartPay/spart-sdks/actions/workflows/dotnet.yml/badge.svg)](https://github.com/SpartPay/spart-sdks/actions/workflows/dotnet.yml) |

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
