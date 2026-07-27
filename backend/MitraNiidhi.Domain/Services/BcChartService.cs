namespace MitraNiidhi.Domain.Services;

/// <summary>
/// BC chart helpers: default month rows + boli ladder (receive amounts step down by deduction).
/// Chart "Boli Amount" / "Random Amount" = what the winner receives (not the discount).
/// Internal bid/discount = totalCollection - receiveAmount.
/// </summary>
public static class BcChartService
{
    public sealed record MonthChartRow(int MonthNumber, decimal RandomAmount, decimal? BoliStartAmount);

    public static IReadOnlyList<MonthChartRow> BuildDefaultChart(
        int totalMembers,
        decimal monthlyContribution,
        decimal? randomStart = null,
        decimal? boliStart = null,
        decimal monthIncrement = 500)
    {
        if (totalMembers < 2)
            throw new ArgumentException("Need at least 2 members.", nameof(totalMembers));

        var collection = monthlyContribution * totalMembers;
        var rndStart = randomStart ?? Math.Round(collection * 0.90m, 0, MidpointRounding.AwayFromZero);
        var boli0 = boliStart ?? Math.Round(collection * 0.85m, 0, MidpointRounding.AwayFromZero);

        var rows = new List<MonthChartRow>(totalMembers);
        for (var month = 1; month <= totalMembers; month++)
        {
            if (month == 1)
            {
                rows.Add(new MonthChartRow(month, collection, null));
                continue;
            }

            if (month == totalMembers)
            {
                rows.Add(new MonthChartRow(month, collection, null));
                continue;
            }

            var offset = month - 2;
            var random = Math.Min(collection, rndStart + offset * monthIncrement);
            var boli = Math.Min(collection - monthIncrement, boli0 + offset * monthIncrement);
            if (boli >= random) boli = random - monthIncrement;
            if (boli <= 0) boli = monthIncrement;
            rows.Add(new MonthChartRow(month, random, boli));
        }

        return rows;
    }

    public static decimal ToDiscount(decimal totalCollection, decimal receiveAmount)
        => Math.Max(0, totalCollection - receiveAmount);

    public static decimal ToReceive(decimal totalCollection, decimal discount)
        => Math.Max(0, totalCollection - discount);

    /// <summary>
    /// Next required boli (receive) amount. First bid = boliStart; later = currentBestReceive - step.
    /// </summary>
    public static decimal? NextBoliReceive(
        decimal? boliStartAmount,
        decimal boliStepAmount,
        decimal? currentBestReceive)
    {
        if (boliStartAmount is null or <= 0) return null;
        if (boliStepAmount <= 0) return null;

        if (currentBestReceive is null)
            return boliStartAmount;

        var next = currentBestReceive.Value - boliStepAmount;
        return next > 0 ? next : null;
    }

    public static bool IsAllowedBoliReceive(
        decimal proposedReceive,
        decimal? boliStartAmount,
        decimal boliStepAmount,
        decimal? currentBestReceive)
    {
        var expected = NextBoliReceive(boliStartAmount, boliStepAmount, currentBestReceive);
        if (expected is null) return false;
        return proposedReceive == expected.Value;
    }
}
