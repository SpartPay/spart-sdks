import { createHmac } from "node:crypto";
import { describe, it, expect } from "vitest";
import { SignatureVerifier } from "../src/signatureVerifier.js";

const SECRET = "whsec_test_abcdef";
const T = 1_700_000_000;

function sign(secret: string, t: number, body: string): string {
  return createHmac("sha256", secret).update(`${t}.`).update(Buffer.from(body, "utf8")).digest("hex");
}

describe("SignatureVerifier", () => {
  it("accepts a correct signature within tolerance", () => {
    const body = '{"id":"evt_1"}';
    const header = `t=${T},v1=${sign(SECRET, T, body)}`;
    expect(new SignatureVerifier(SECRET).verify(body, header, T)).toBe(true);
  });

  it("rejects a tampered body", () => {
    const body = '{"id":"evt_1"}';
    const header = `t=${T},v1=${sign(SECRET, T, body)}`;
    expect(new SignatureVerifier(SECRET).verify('{"id":"evt_2"}', header, T)).toBe(false);
  });

  it("rejects a wrong secret", () => {
    const body = '{"id":"evt_1"}';
    const header = `t=${T},v1=${sign("other", T, body)}`;
    expect(new SignatureVerifier(SECRET).verify(body, header, T)).toBe(false);
  });

  it("rejects stale timestamps on both sides of the window", () => {
    const body = '{"id":"evt_1"}';
    const header = `t=${T},v1=${sign(SECRET, T, body)}`;
    const v = new SignatureVerifier(SECRET);
    expect(v.verify(body, header, T + 301)).toBe(false);
    expect(v.verify(body, header, T - 301)).toBe(false);
  });

  it("accepts exactly at the tolerance boundary", () => {
    const body = '{"id":"evt_1"}';
    const header = `t=${T},v1=${sign(SECRET, T, body)}`;
    expect(new SignatureVerifier(SECRET).verify(body, header, T + 300)).toBe(true);
  });

  it("rejects malformed headers", () => {
    const v = new SignatureVerifier(SECRET);
    expect(v.verify("body", "garbage", T)).toBe(false);
    expect(v.verify("body", "t=abc,v1=def", T)).toBe(false);
    expect(v.verify("body", "v1=abc", T)).toBe(false);
    expect(v.verify("body", `t=${T}`, T)).toBe(false);
    expect(v.verify("body", "", T)).toBe(false);
  });

  it("parses reversed order and trims, last v1 wins", () => {
    expect(SignatureVerifier.parseHeader("v1=abcdef,t=100")).toEqual({ t: 100, v1: "abcdef" });
    expect(SignatureVerifier.parseHeader("  t=100 , v1=abcdef  ")).toEqual({ t: 100, v1: "abcdef" });
    expect(SignatureVerifier.parseHeader("t=100,v1=first,v1=second")).toEqual({ t: 100, v1: "second" });
  });

  it("rejects a blank secret and an out-of-range tolerance", () => {
    expect(() => new SignatureVerifier("")).toThrow();
    expect(() => new SignatureVerifier("s", 0)).toThrow();
    expect(() => new SignatureVerifier("s", 86_401)).toThrow();
  });
});
