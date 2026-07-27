using FluentAssertions;
using MitraNiidhi.Application.Common;
using MitraNiidhi.Application.Members;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Tests;

public class MultiHandSeatTests
{
    [Theory]
    [InlineData("Akhilesh", null, 3, "Akhilesh (#3)")]
    [InlineData("Akhilesh", "Hand 2", 11, "Akhilesh · Hand 2 (#11)")]
    public void FormatDisplayName_IncludesHandAndNumber(string name, string? hand, int number, string expected)
    {
        SeatHelper.FormatDisplayName(name, hand, number).Should().Be(expected);
    }

    [Fact]
    public void AssignMemberRequest_AddHandDefaultsFalse()
    {
        var req = new AssignMemberRequest(1, null, null, null, null, null, null, null);
        req.AddHand.Should().BeFalse();
    }

    [Fact]
    public void GroupMember_DisplayName_UsesHandLabelWhenPresent()
    {
        var seat = new GroupMember
        {
            MemberNumber = 7,
            HandLabel = "Hand 2",
            Member = new Member { MemberName = "Akhilesh" }
        };
        seat.DisplayName.Should().Be("Akhilesh · Hand 2");
    }

    [Fact]
    public void GroupMember_DisplayName_FallsBackToMemberName()
    {
        var seat = new GroupMember
        {
            MemberNumber = 1,
            Member = new Member { MemberName = "Ravi" }
        };
        seat.DisplayName.Should().Be("Ravi");
    }

    [Fact]
    public void ApproveWinnerRequest_AcceptsWinnerGroupMemberId()
    {
        var req = new MitraNiidhi.Application.Bidding.ApproveWinnerRequest(3, 10, 5000m, 99);
        req.WinnerGroupMemberId.Should().Be(99);
        req.WinnerMemberId.Should().Be(10);
    }

    [Fact]
    public void PlaceBidRequest_AcceptsGroupMemberId()
    {
        var req = new MitraNiidhi.Application.Bidding.PlaceBidRequest(2, 85000m, 55);
        req.GroupMemberId.Should().Be(55);
        req.BoliAmount.Should().Be(85000m);
    }
}
