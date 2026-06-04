# @spartpay/sdk

[![npm version](https://img.shields.io/npm/v/@spartpay/sdk.svg?logo=npm)](https://www.npmjs.com/package/@spartpay/sdk)
[![npm downloads](https://img.shields.io/npm/dm/@spartpay/sdk.svg)](https://www.npmjs.com/package/@spartpay/sdk)
[![node CI](https://github.com/SpartPay/spart-sdks/actions/workflows/node.yml/badge.svg)](https://github.com/SpartPay/spart-sdks/actions/workflows/node.yml)

Spart webhook signature verification for Node.js. Zero runtime dependencies.

## Install

```bash
npm install @spartpay/sdk
```

## Usage

```ts
import { SignatureVerifier } from "@spartpay/sdk";

const verifier = new SignatureVerifier(process.env.SPART_WEBHOOK_SECRET!);

// rawBody MUST be the exact request bytes (Buffer or string), read BEFORE
// any JSON parsing. With Express:
//   express.json({ verify: (req, _res, buf) => { req.rawBody = buf; } })
const ok = verifier.verify(req.rawBody, req.get("X-Spart-Signature") ?? "");
if (!ok) return res.status(401).end();
```
