using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Groups;

/// <summary>Existing member id and/or a new display name for a seat.</summary>
public record CreateGroupMemberInput(int? MemberId, string? MemberName);

public record CreateGroupRequest(
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    DateOnly StartDate,
    IReadOnlyList<CreateGroupMemberInput>? Members = null,
    IReadOnlyList<string>? MemberNames = null,
    /// <summary>0-based index into Members / MemberNames — who receives Month 1 pot.</summary>
    int? OrganiserSlotIndex = null);

public record UpdateGroupRequest(
    string GroupName,
    DateOnly StartDate,
    string Status,
    decimal MonthlyContribution,
    int? OrganiserMemberId = null,
    int? OrganiserGroupMemberId = null);

public record CloneGroupRequest(
    string NewGroupName,
    DateOnly StartDate,
    IReadOnlyList<int> SelectedMemberIds,
    IReadOnlyList<string>? NewMemberNames);
