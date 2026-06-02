# Spart.Sdk

Spart webhook signature verification for .NET. No external dependencies.

## Install

```bash
dotnet add package Spart.Sdk
```

## Usage

```csharp
using Spart.Sdk;

var verifier = new SignatureVerifier(
    Environment.GetEnvironmentVariable("SPART_WEBHOOK_SECRET")!);

// Read the exact request bytes BEFORE model binding / JSON parsing.
using var ms = new MemoryStream();
await request.Body.CopyToAsync(ms);
var ok = verifier.Verify(ms.ToArray(), request.Headers["X-Spart-Signature"].ToString());
if (!ok) return Results.Unauthorized();
```

The optional third parameter, `now`, is a `DateTimeOffset?` (defaulting to
`DateTimeOffset.UtcNow`) used to evaluate the timestamp tolerance. Pass an
explicit value in tests to verify boundary behaviour deterministically.
