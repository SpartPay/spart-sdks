from __future__ import annotations

import hashlib
import hmac
import re
import time

_DEFAULT_TOLERANCE_SECONDS = 300
_MAX_TOLERANCE_SECONDS = 86_400
_DIGITS = re.compile(r"^[0-9]+$")


class SignatureVerifier:
    """Verifies inbound Spart webhook signatures.

    Header format: ``X-Spart-Signature: t=<unix-seconds>,v1=<lowercase-hex>``.
    Signature: HMAC-SHA256 over the bytes ``f"{t}.".encode() + raw_body``.
    """

    def __init__(self, secret: str, tolerance_seconds: int = _DEFAULT_TOLERANCE_SECONDS) -> None:
        if secret.strip() == "":
            raise ValueError("SignatureVerifier: secret must not be blank.")
        if not 1 <= tolerance_seconds <= _MAX_TOLERANCE_SECONDS:
            raise ValueError("SignatureVerifier: tolerance_seconds must be between 1 and 86400.")
        self._secret = secret.encode("utf-8")
        self._tolerance_seconds = tolerance_seconds

    def verify(self, raw_body: bytes, signature_header: str, now: int | None = None) -> bool:
        parsed = self.parse_header(signature_header)
        if parsed is None:
            return False
        t, v1 = parsed

        current = int(time.time()) if now is None else now
        if abs(current - t) > self._tolerance_seconds:
            return False

        signed = f"{t}.".encode("utf-8") + raw_body
        expected = hmac.new(self._secret, signed, hashlib.sha256).hexdigest()
        # Compare on bytes: hmac.compare_digest raises on non-ASCII str operands,
        # whereas v1 is attacker-controlled. Encoding lets a non-hex v1 fail
        # closed (return False) instead of raising, matching the other SDKs.
        return hmac.compare_digest(expected.encode("ascii"), v1.encode("utf-8"))

    @staticmethod
    def parse_header(value: str) -> tuple[int, str] | None:
        if value == "":
            return None
        t: int | None = None
        v1: str | None = None
        for part in value.split(","):
            part = part.strip()
            if part.startswith("t="):
                raw = part[2:]
                if not _DIGITS.match(raw):
                    return None
                t = int(raw)
            elif part.startswith("v1="):
                v1 = part[3:]
        if t is None or v1 is None or v1 == "":
            return None
        return t, v1
