using System.Text;
using System.Text.Json;
using Spart.Sdk;
using Xunit;

namespace Spart.Sdk.Tests;

public class FixturesTests
{
    public sealed record FixtureCase(
        string Name, long Now, string? RawBodyUtf8, string? RawBodyBase64, string SignatureHeader, bool Expected);

    private static string Secret { get; } = LoadSecret();

    private static string LoadSecret()
    {
        using var doc = JsonDocument.Parse(File.ReadAllText(FixturesPath));
        return doc.RootElement.GetProperty("secret").GetString()!;
    }

    private static string FixturesPath => Path.Combine(AppContext.BaseDirectory, "fixtures.json");

    public static IEnumerable<object[]> Cases()
    {
        using var doc = JsonDocument.Parse(File.ReadAllText(FixturesPath));
        foreach (var c in doc.RootElement.GetProperty("cases").EnumerateArray())
        {
            yield return new object[]
            {
                new FixtureCase(
                    c.GetProperty("name").GetString()!,
                    c.GetProperty("now").GetInt64(),
                    c.TryGetProperty("rawBodyUtf8", out var u) ? u.GetString() : null,
                    c.TryGetProperty("rawBodyBase64", out var b) ? b.GetString() : null,
                    c.GetProperty("signatureHeader").GetString()!,
                    c.GetProperty("expected").GetBoolean()),
            };
        }
    }

    [Theory]
    [MemberData(nameof(Cases))]
    public void Shared_cross_language_fixture(FixtureCase c)
    {
        var body = c.RawBodyBase64 is not null
            ? Convert.FromBase64String(c.RawBodyBase64)
            : Encoding.UTF8.GetBytes(c.RawBodyUtf8!);
        var verifier = new SignatureVerifier(Secret);

        var result = verifier.Verify(body, c.SignatureHeader, DateTimeOffset.FromUnixTimeSeconds(c.Now));

        Assert.Equal(c.Expected, result);
    }
}
