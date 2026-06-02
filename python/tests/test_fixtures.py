import base64
import json
from pathlib import Path

import pytest

from spart_sdk import SignatureVerifier

_FIXTURE = json.loads((Path(__file__).resolve().parents[2] / "fixtures.json").read_text("utf-8"))
_CASES = _FIXTURE["cases"]


def _body(case: dict) -> bytes:
    if "rawBodyBase64" in case:
        return base64.b64decode(case["rawBodyBase64"])
    return case["rawBodyUtf8"].encode("utf-8")


@pytest.mark.parametrize("case", _CASES, ids=[c["name"] for c in _CASES])
def test_shared_cross_language_fixture(case):
    verifier = SignatureVerifier(_FIXTURE["secret"])
    result = verifier.verify(_body(case), case["signatureHeader"], now=case["now"])
    assert result is case["expected"]
