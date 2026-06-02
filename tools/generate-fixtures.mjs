// Generates the shared webhook signature fixtures consumed by every language
// suite. Run with `node tools/generate-fixtures.mjs` and commit the output.
// Re-running with the same constants reproduces byte-identical fixtures.json.
//
// The fixture set deliberately pins cross-language PARITY, not just "HMAC works":
// reordered/unknown/duplicate/empty header segments, case-sensitive hex,
// non-ASCII-digit rejection, tolerance boundaries, and a non-UTF-8 binary body.
// Each case carries `expected` and exactly one of `rawBodyUtf8` / `rawBodyBase64`.
import { createHmac } from "node:crypto";
import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const SECRET = "whsec_test_5f4dcc3b5aa765d61d8327deb882cf99";
const T = 1700000000;

// Multibyte char + nontrivial structure: proves every language signs the exact
// UTF-8 bytes and never a re-serialized object.
const BODY =
  '{"id":"evt_test_1","type":"order.completed","data":{"order":{"id":"ord_1","amount":1299}},"note":"café über"}';

// Arbitrary non-UTF-8 byte sequence: proves the body is treated as raw bytes,
// not a (possibly lossy) UTF-8 string, across all four languages.
const BINARY_BODY = Buffer.from([0x00, 0xff, 0x10, 0x80, 0x7f, 0xc3, 0x28]);

function sign(t, bodyBuf) {
  return createHmac("sha256", SECRET).update(`${t}.`).update(bodyBuf).digest("hex");
}

const bodyBuf = Buffer.from(BODY, "utf8");
const v1 = sign(T, bodyBuf);
const baseHeader = `t=${T},v1=${v1}`;

// Arabic-Indic rendering of the same digits; must be rejected everywhere.
const arabicIndicT = String(T).replace(/[0-9]/g, (d) => "٠١٢٣٤٥٦٧٨٩"[Number(d)]);

const binaryV1 = sign(T, BINARY_BODY);

const cases = [
  {
    name: "valid_utf8_body",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: baseHeader,
    expected: true,
  },
  {
    name: "tampered_body_one_byte",
    now: T,
    rawBodyUtf8: BODY + " ",
    signatureHeader: baseHeader,
    expected: false,
  },
  {
    name: "reordered_segments",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: `v1=${v1},t=${T}`,
    expected: true,
  },
  {
    name: "unknown_segment_ignored",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: `t=${T},v1=${v1},foo=bar`,
    expected: true,
  },
  {
    name: "duplicate_v1_last_wins",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: `t=${T},v1=deadbeef,v1=${v1}`,
    expected: true,
  },
  {
    name: "empty_and_trailing_segments_ignored",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: `t=${T},,v1=${v1},`,
    expected: true,
  },
  {
    name: "uppercase_hex_rejected",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: `t=${T},v1=${v1.toUpperCase()}`,
    expected: false,
  },
  {
    name: "non_ascii_digit_t_rejected",
    now: T,
    rawBodyUtf8: BODY,
    signatureHeader: `t=${arabicIndicT},v1=${v1}`,
    expected: false,
  },
  {
    name: "stale_timestamp_rejected",
    now: T + 301,
    rawBodyUtf8: BODY,
    signatureHeader: baseHeader,
    expected: false,
  },
  {
    name: "future_timestamp_rejected",
    now: T - 301,
    rawBodyUtf8: BODY,
    signatureHeader: baseHeader,
    expected: false,
  },
  {
    name: "boundary_plus_tolerance_accepted",
    now: T + 300,
    rawBodyUtf8: BODY,
    signatureHeader: baseHeader,
    expected: true,
  },
  {
    name: "binary_non_utf8_body",
    now: T,
    rawBodyBase64: BINARY_BODY.toString("base64"),
    signatureHeader: `t=${T},v1=${binaryV1}`,
    expected: true,
  },
];

const fixture = { version: 2, secret: SECRET, cases };

const out = resolve(dirname(fileURLToPath(import.meta.url)), "..", "fixtures.json");
writeFileSync(out, JSON.stringify(fixture, null, 2) + "\n", "utf8");
console.log(`Wrote ${out} (${cases.length} cases)`);
