namespace MitraNiidhi.Application.Common;

public static class PhoneNormalizer
{
    public static string DigitsOnly(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return string.Empty;
        return new string(value.Where(char.IsDigit).ToArray());
    }

    /// <summary>Indian mobiles: compare last 10 digits.</summary>
    public static string? Last10Digits(string? value)
    {
        var digits = DigitsOnly(value);
        if (digits.Length < 10) return digits.Length == 0 ? null : digits;
        return digits[^10..];
    }

    public static bool LooksLikePhoneLogin(string login)
    {
        var digits = DigitsOnly(login);
        // At least 10 digits; ignore formatting like +91 / spaces / dashes.
        return digits.Length >= 10;
    }

    public static bool Matches(string? storedPhone, string loginOrDigits)
    {
        var a = Last10Digits(storedPhone);
        var b = Last10Digits(loginOrDigits);
        return a is not null && b is not null && a == b;
    }
}
