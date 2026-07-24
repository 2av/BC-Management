namespace MitraNiidhi.Application.Payments;

public static class UpiPaymentHelper
{
    public const string BrandPayee = "Mitra Niidhi Samooh";

    public static string PaymentNote(string? groupName = null, int? monthNumber = null)
    {
        var note = string.IsNullOrWhiteSpace(groupName)
            ? BrandPayee
            : $"{BrandPayee} - {groupName.Trim()}";
        if (monthNumber is int m && m > 0)
            note = $"{note} M{m}";
        return note;
    }

    public static (string UpiUrl, string QrImageUrl) BuildUrls(
        string upiId,
        string payee,
        string note,
        decimal? amount = null)
    {
        var am = amount is decimal a && a > 0
            ? $"&am={a:0.##}"
            : "";
        var upiUrl =
            $"upi://pay?pa={Uri.EscapeDataString(upiId)}&pn={Uri.EscapeDataString(payee)}{am}&cu=INR&tn={Uri.EscapeDataString(note)}";
        var qrImageUrl =
            $"https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={Uri.EscapeDataString(upiUrl)}";
        return (upiUrl, qrImageUrl);
    }

    /// <summary>
    /// Admin-set due → bid/random gain → BC monthly contribution.
    /// </summary>
    public static decimal ResolveDueAmount(
        decimal monthlyContribution,
        decimal? paymentDueAmount,
        decimal? gainPerMember = null)
    {
        if (paymentDueAmount is decimal due && due > 0)
            return due;
        if (gainPerMember is decimal gain && gain > 0)
            return gain;
        return monthlyContribution;
    }
}
