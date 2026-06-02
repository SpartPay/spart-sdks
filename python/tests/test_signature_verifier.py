import hashlib
import hmac

import pytest

from spart_sdk import SignatureVerifier

SECRET = "whsec_test_abcdef"
T = 1_700_000_000


def sign(secret: str, t: int, body: bytes) -> str:
    return hmac.new(secret.encode(), f"{t}.".encode() + body, hashlib.sha256).hexdigest()


def test_accepts_correct_signature_within_tolerance():
    body = b'{"id":"evt_1"}'
    header = f"t={T},v1={sign(SECRET, T, body)}"
    assert SignatureVerifier(SECRET).verify(body, header, now=T) is True


def test_rejects_tampered_body():
    body = b'{"id":"evt_1"}'
    header = f"t={T},v1={sign(SECRET, T, body)}"
    assert SignatureVerifier(SECRET).verify(b'{"id":"evt_2"}', header, now=T) is False


def test_rejects_wrong_secret():
    body = b'{"id":"evt_1"}'
    header = f"t={T},v1={sign('other', T, body)}"
    assert SignatureVerifier(SECRET).verify(body, header, now=T) is False


def test_rejects_stale_timestamp_both_sides():
    body = b'{"id":"evt_1"}'
    header = f"t={T},v1={sign(SECRET, T, body)}"
    verifier = SignatureVerifier(SECRET)
    assert verifier.verify(body, header, now=T + 301) is False
    assert verifier.verify(body, header, now=T - 301) is False


def test_accepts_exactly_at_boundary():
    body = b'{"id":"evt_1"}'
    header = f"t={T},v1={sign(SECRET, T, body)}"
    assert SignatureVerifier(SECRET).verify(body, header, now=T + 300) is True


@pytest.mark.parametrize(
    "header",
    ["garbage", "t=abc,v1=def", "v1=abc", f"t={T}", ""],
)
def test_rejects_malformed_headers(header):
    assert SignatureVerifier(SECRET).verify(b"body", header, now=T) is False


def test_parse_header_reversed_trimmed_last_wins():
    assert SignatureVerifier.parse_header("v1=abcdef,t=100") == (100, "abcdef")
    assert SignatureVerifier.parse_header("  t=100 , v1=abcdef  ") == (100, "abcdef")
    assert SignatureVerifier.parse_header("t=100,v1=first,v1=second") == (100, "second")


def test_constructor_rejects_blank_secret_and_bad_tolerance():
    with pytest.raises(ValueError):
        SignatureVerifier("")
    with pytest.raises(ValueError):
        SignatureVerifier("s", 0)
    with pytest.raises(ValueError):
        SignatureVerifier("s", 86_401)


def test_rejects_non_ascii_unicode_digits_in_t():
    # Parity guard: Node/.NET/PHP accept only ASCII [0-9] in t; Python's \d
    # must not accept Unicode decimal digits (e.g. Arabic-Indic) or the four
    # SDKs would disagree on the same header.
    body = b'{"id":"evt_1"}'
    valid_v1 = sign(SECRET, T, body)
    arabic_indic_t = "١٧٠٠٠٠٠٠٠٠"
    header = f"t={arabic_indic_t},v1={valid_v1}"
    assert SignatureVerifier.parse_header(header) is None
    assert SignatureVerifier(SECRET).verify(body, header, now=T) is False


def test_returns_false_on_non_ascii_v1_without_raising():
    # hmac.compare_digest raises TypeError on non-ASCII str operands; verify()
    # must fail closed (return False), matching the byte-based comparison the
    # other three SDKs use.
    body = b'{"id":"evt_1"}'
    header = f"t={T},v1=café{sign(SECRET, T, body)}"
    assert SignatureVerifier(SECRET).verify(body, header, now=T) is False
