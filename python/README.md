# spart-sdk

Spart webhook signature verification for Python. Standard library only.

## Install

```bash
pip install spart-sdk
```

## Usage

```python
from spart_sdk import SignatureVerifier

verifier = SignatureVerifier(os.environ["SPART_WEBHOOK_SECRET"])

# raw_body MUST be the exact request bytes, read BEFORE JSON parsing.
# Flask: request.get_data()
if not verifier.verify(raw_body, request.headers.get("X-Spart-Signature", "")):
    return "invalid signature", 401
```
