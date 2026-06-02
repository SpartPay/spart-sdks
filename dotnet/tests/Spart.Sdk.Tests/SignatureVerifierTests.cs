using System.Security.Cryptography;
using System.Text;
using Spart.Sdk;
using Xunit;

namespace Spart.Sdk.Tests;

public class SignatureVerifierTests
{
    private const string Secret = "whsec_test_abcdef";
    private const long T = 1_700_000_000;

    private static string Sign(string secret, long t, string body)
    {
        var message = Encoding.UTF8.GetBytes($"{t}.{body}");
        var hash = HMACSHA256.HashData(Encoding.UTF8.GetBytes(secret), message);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private static DateTimeOffset At(long unixSeconds) => DateTimeOffset.FromUnixTimeSeconds(unixSeconds);

    [Fact]
    public void Accepts_correct_signature_within_tolerance()
    {
        const string body = "{\"id\":\"evt_1\"}";
        var header = $"t={T},v1={Sign(Secret, T, body)}";
        Assert.True(new SignatureVerifier(Secret).Verify(body, header, At(T)));
    }

    [Fact]
    public void Rejects_tampered_body()
    {
        const string body = "{\"id\":\"evt_1\"}";
        var header = $"t={T},v1={Sign(Secret, T, body)}";
        Assert.False(new SignatureVerifier(Secret).Verify("{\"id\":\"evt_2\"}", header, At(T)));
    }

    [Fact]
    public void Rejects_wrong_secret()
    {
        const string body = "{\"id\":\"evt_1\"}";
        var header = $"t={T},v1={Sign("other", T, body)}";
        Assert.False(new SignatureVerifier(Secret).Verify(body, header, At(T)));
    }

    [Fact]
    public void Rejects_stale_timestamp_both_sides()
    {
        const string body = "{\"id\":\"evt_1\"}";
        var header = $"t={T},v1={Sign(Secret, T, body)}";
        var verifier = new SignatureVerifier(Secret);
        Assert.False(verifier.Verify(body, header, At(T + 301)));
        Assert.False(verifier.Verify(body, header, At(T - 301)));
    }

    [Fact]
    public void Accepts_exactly_at_boundary()
    {
        const string body = "{\"id\":\"evt_1\"}";
        var header = $"t={T},v1={Sign(Secret, T, body)}";
        Assert.True(new SignatureVerifier(Secret).Verify(body, header, At(T + 300)));
    }

    [Theory]
    [InlineData("garbage")]
    [InlineData("t=abc,v1=def")]
    [InlineData("v1=abc")]
    [InlineData("t=1700000000")]
    [InlineData("")]
    public void Rejects_malformed_headers(string header)
    {
        Assert.False(new SignatureVerifier(Secret).Verify("body", header, At(T)));
    }

    [Fact]
    public void Constructor_rejects_blank_secret_and_bad_tolerance()
    {
        Assert.Throws<ArgumentException>(() => new SignatureVerifier(""));
        Assert.Throws<ArgumentOutOfRangeException>(() => new SignatureVerifier("s", 0));
        Assert.Throws<ArgumentOutOfRangeException>(() => new SignatureVerifier("s", 86_401));
    }
}
