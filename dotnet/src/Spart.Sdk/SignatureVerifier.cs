using System.Globalization;
using System.Security.Cryptography;
using System.Text;

namespace Spart.Sdk;

/// <summary>
/// Verifies inbound Spart webhook signatures. Header format:
/// <c>X-Spart-Signature: t=&lt;unix-seconds&gt;,v1=&lt;lowercase-hex&gt;</c>.
/// Signature: HMAC-SHA256 over the bytes <c>"{t}." + rawBody</c>.
/// </summary>
public sealed class SignatureVerifier
{
    private const int DefaultToleranceSeconds = 300;
    private const int MaxToleranceSeconds = 86_400;

    private readonly byte[] _secret;
    private readonly int _toleranceSeconds;

    public SignatureVerifier(string secret, int toleranceSeconds = DefaultToleranceSeconds)
    {
        if (string.IsNullOrWhiteSpace(secret))
        {
            throw new ArgumentException("Secret must not be blank.", nameof(secret));
        }
        if (toleranceSeconds is < 1 or > MaxToleranceSeconds)
        {
            throw new ArgumentOutOfRangeException(
                nameof(toleranceSeconds), "toleranceSeconds must be between 1 and 86400.");
        }

        _secret = Encoding.UTF8.GetBytes(secret);
        _toleranceSeconds = toleranceSeconds;
    }

    public bool Verify(ReadOnlySpan<byte> rawBody, string signatureHeader, DateTimeOffset? now = null)
    {
        if (!TryParseHeader(signatureHeader, out var t, out var v1))
        {
            return false;
        }

        var current = (now ?? DateTimeOffset.UtcNow).ToUnixTimeSeconds();
        if (Math.Abs(current - t) > _toleranceSeconds)
        {
            return false;
        }

        var prefix = Encoding.UTF8.GetBytes($"{t}.");
        var message = new byte[prefix.Length + rawBody.Length];
        prefix.CopyTo(message, 0);
        rawBody.CopyTo(message.AsSpan(prefix.Length));

        var expected = Convert.ToHexString(HMACSHA256.HashData(_secret, message)).ToLowerInvariant();
        var expectedBytes = Encoding.UTF8.GetBytes(expected);
        var providedBytes = Encoding.UTF8.GetBytes(v1);
        if (expectedBytes.Length != providedBytes.Length)
        {
            return false;
        }
        return CryptographicOperations.FixedTimeEquals(expectedBytes, providedBytes);
    }

    public bool Verify(string rawBody, string signatureHeader, DateTimeOffset? now = null)
        => Verify(Encoding.UTF8.GetBytes(rawBody), signatureHeader, now);

    internal static bool TryParseHeader(string value, out long t, out string v1)
    {
        t = 0;
        v1 = string.Empty;
        if (string.IsNullOrEmpty(value))
        {
            return false;
        }

        long? parsedT = null;
        string? parsedV1 = null;
        foreach (var part in value.Split(','))
        {
            var trimmed = part.Trim();
            if (trimmed.StartsWith("t=", StringComparison.Ordinal))
            {
                var raw = trimmed[2..];
                if (raw.Length == 0 || !raw.All(char.IsAsciiDigit))
                {
                    return false;
                }
                if (!long.TryParse(raw, NumberStyles.None, CultureInfo.InvariantCulture, out var pt))
                {
                    // All-digit but outside Int64 range — reject rather than throw.
                    return false;
                }
                parsedT = pt;
            }
            else if (trimmed.StartsWith("v1=", StringComparison.Ordinal))
            {
                parsedV1 = trimmed[3..];
            }
        }

        if (parsedT is null || string.IsNullOrEmpty(parsedV1))
        {
            return false;
        }
        t = parsedT.Value;
        v1 = parsedV1;
        return true;
    }
}
