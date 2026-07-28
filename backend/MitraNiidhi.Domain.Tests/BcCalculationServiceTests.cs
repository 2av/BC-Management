using FluentAssertions;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Domain.Tests;

public class BcCalculationServiceTests
{
    [Fact]
    public void NoBid_UsesFullContribution()
    {
        var result = BcCalculationService.CalculateMonth(2000, 9, 0, false);

        result.NetPayable.Should().Be(18000);
        result.GainPerMember.Should().Be(2000);
        result.BidAmount.Should().Be(0);
        result.IsBid.Should().BeFalse();
    }

    [Fact]
    public void WithBid_MatchesFamilyBcSampleMonth2()
    {
        // Sample: contribution 2000, 9 members, bid 1000 → net 17000, gain 1889
        var result = BcCalculationService.CalculateMonth(2000, 9, 1000, true);

        result.NetPayable.Should().Be(17000);
        result.GainPerMember.Should().Be(1889);
        result.IsBid.Should().BeTrue();
    }

    [Fact]
    public void WithBid_MatchesFamilyBcSampleMonth3()
    {
        var result = BcCalculationService.CalculateMonth(2000, 9, 800, true);

        result.NetPayable.Should().Be(17200);
        result.GainPerMember.Should().Be(1911);
    }

    [Fact]
    public void Profit_IsGivenMinusPaid()
    {
        BcCalculationService.Profit(18000, 7711).Should().Be(10289);
    }

    [Fact]
    public void TooFewMembers_Throws()
    {
        var act = () => BcCalculationService.CalculateMonth(2000, 1, 0, false);
        act.Should().Throw<ArgumentException>();
    }

    [Fact]
    public void LargeBid_StillDistributesRemainder()
    {
        // contribution 5000, 10 members, bid 2000 → collection 50000, net 48000, gain 4800
        var result = BcCalculationService.CalculateMonth(5000, 10, 2000, true);
        result.NetPayable.Should().Be(48000);
        result.GainPerMember.Should().Be(4800);
        result.BidAmount.Should().Be(2000);
    }

    [Theory]
    [InlineData(18000, 18000, 0)]
    [InlineData(10000, 12000, -2000)]
    [InlineData(0, 0, 0)]
    public void Profit_HandlesEdgeCases(decimal given, decimal paid, decimal expected)
    {
        BcCalculationService.Profit(given, paid).Should().Be(expected);
    }

    [Fact]
    public void TotalMonthlyCollection_IsContributionTimesMembers()
    {
        BcCalculationService.TotalMonthlyCollection(2000, 9).Should().Be(18000);
    }

    [Fact]
    public void TrySyncStoredCollection_CorrectsStaleDenormalizedValue()
    {
        var group = new MitraNiidhi.Domain.Entities.BcGroup
        {
            MonthlyContribution = 3000,
            TotalMembers = 15,
            TotalMonthlyCollection = 48000,
        };

        BcCalculationService.TrySyncStoredCollection(group).Should().BeTrue();
        group.TotalMonthlyCollection.Should().Be(45000);
        BcCalculationService.TrySyncStoredCollection(group).Should().BeFalse();
    }

    [Fact]
    public void NoBid_IgnoresPositiveBidAmountWhenIsBidFalse()
    {
        var result = BcCalculationService.CalculateMonth(2000, 9, 500, isBid: false);
        result.BidAmount.Should().Be(0);
        result.NetPayable.Should().Be(18000);
        result.IsBid.Should().BeFalse();
    }
}
