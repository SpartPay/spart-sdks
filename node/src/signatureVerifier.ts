import { createHmac, timingSafeEqual } from "node:crypto";

const DEFAULT_TOLERANCE_SECONDS = 300;
const MAX_TOLERANCE_SECONDS = 86_400;

export class SignatureVerifier {
  private readonly secret: string;
  private readonly toleranceSeconds: number;

  constructor(secret: string, toleranceSeconds: number = DEFAULT_TOLERANCE_SECONDS) {
    if (secret.trim() === "") {
      throw new Error("SignatureVerifier: secret must not be blank.");
    }
    if (toleranceSeconds < 1 || toleranceSeconds > MAX_TOLERANCE_SECONDS) {
      throw new Error("SignatureVerifier: toleranceSeconds must be between 1 and 86400.");
    }
    this.secret = secret;
    this.toleranceSeconds = toleranceSeconds;
  }

  verify(rawBody: Buffer | string, signatureHeader: string, now?: number): boolean {
    const parsed = SignatureVerifier.parseHeader(signatureHeader);
    if (parsed === null) {
      return false;
    }
    const { t, v1 } = parsed;

    const current = now ?? Math.floor(Date.now() / 1000);
    if (Math.abs(current - t) > this.toleranceSeconds) {
      return false;
    }

    const body = typeof rawBody === "string" ? Buffer.from(rawBody, "utf8") : rawBody;
    const expected = createHmac("sha256", this.secret).update(`${t}.`).update(body).digest("hex");

    const expectedBytes = Buffer.from(expected, "utf8");
    const providedBytes = Buffer.from(v1, "utf8");
    if (expectedBytes.length !== providedBytes.length) {
      return false;
    }
    return timingSafeEqual(expectedBytes, providedBytes);
  }

  static parseHeader(value: string): { t: number; v1: string } | null {
    if (value === "") {
      return null;
    }
    let t: number | null = null;
    let v1: string | null = null;
    for (const part of value.split(",")) {
      const trimmed = part.trim();
      if (trimmed.startsWith("t=")) {
        const raw = trimmed.slice(2);
        if (!/^\d+$/.test(raw)) {
          return null;
        }
        t = Number(raw);
      } else if (trimmed.startsWith("v1=")) {
        v1 = trimmed.slice(3);
      }
    }
    if (t === null || v1 === null || v1 === "") {
      return null;
    }
    return { t, v1 };
  }
}
