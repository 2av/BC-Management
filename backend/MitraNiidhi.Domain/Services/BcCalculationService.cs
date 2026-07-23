namespace MitraNiidhi.Domain.Services;

/// <summary>
/// Pure BC/Niidhi calculation rules — single source of truth for all portals.
/// Matches existing PHP sample / Excel-style logic:
/// net = totalCollection - bid; gainPerMember = round(net / totalMembers).
/// </summary>
public static class BcCalculationService
{
    public sealed record MonthSettlement(
        decimal BidAmount,
        decimal NetPayable,
        decimal GainPerMember,
        bool IsBid);

    public static MonthSettlement CalculateMonth(
        decimal monthlyContribution,
        int totalMembers,
        decimal bidAmount,
        bool isBid)
    {
        if (totalMembers < 2)
            throw new ArgumentException("A BC group requires at least 2 members.", nameof(totalMembers));

        var totalCollection = monthlyContribution * totalMembers;

        if (!isBid || bidAmount <= 0)
        {
            return new MonthSettlement(
                BidAmount: 0,
                NetPayable: totalCollection,
                GainPerMember: monthlyContribution,
                IsBid: false);
        }

        var netPayable = totalCollection - bidAmount;
        var gainPerMember = Math.Round(netPayable / totalMembers, 0, MidpointRounding.AwayFromZero);

        return new MonthSettlement(
            BidAmount: bidAmount,
            NetPayable: netPayable,
            GainPerMember: gainPerMember,
            IsBid: true);
    }

    public static decimal TotalMonthlyCollection(decimal monthlyContribution, int totalMembers)
        => monthlyContribution * totalMembers;

    public static decimal Profit(decimal givenAmount, decimal totalPaid)
        => givenAmount - totalPaid;
}
