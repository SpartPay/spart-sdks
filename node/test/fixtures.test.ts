import { readFileSync } from "node:fs";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, it, expect } from "vitest";
import { SignatureVerifier } from "../src/signatureVerifier.js";

interface FixtureCase {
  name: string;
  now: number;
  rawBodyUtf8?: string;
  rawBodyBase64?: string;
  signatureHeader: string;
  expected: boolean;
}

const fixturesPath = resolve(dirname(fileURLToPath(import.meta.url)), "..", "..", "fixtures.json");
const fixture = JSON.parse(readFileSync(fixturesPath, "utf8")) as {
  version: number;
  secret: string;
  cases: FixtureCase[];
};

function body(c: FixtureCase): Buffer {
  if (c.rawBodyBase64 !== undefined) {
    return Buffer.from(c.rawBodyBase64, "base64");
  }
  return Buffer.from(c.rawBodyUtf8 ?? "", "utf8");
}

describe("shared cross-language fixtures", () => {
  const verifier = new SignatureVerifier(fixture.secret);

  it.each(fixture.cases)("$name", (c) => {
    expect(verifier.verify(body(c), c.signatureHeader, c.now)).toBe(c.expected);
  });
});
