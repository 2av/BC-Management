namespace MitraNiidhi.Application.Members;

public record MemberGroupBriefDto(
    int GroupMemberId,
    int GroupId,
    string GroupName,
    int MemberNumber,
    string? HandLabel,
    string Status,
    DateTime JoinedDate);

public record MemberListItemDto(
    int Id,
    string MemberName,
    string? Username,
    string? Phone,
    string? Email,
    string? Address,
    string Status,
    int GroupCount,
    IReadOnlyList<MemberGroupBriefDto> Groups);

public record CreateMemberRequest(
    string MemberName,
    string? Username,
    string? Password,
    string? Phone,
    string? Email,
    string? Address,
    int? GroupId,
    int? MemberNumber);

public record UpdateMemberRequest(
    string MemberName,
    string? Username,
    string? Phone,
    string? Email,
    string? Address,
    string Status,
    string? NewPassword = null);

public record AssignMemberRequest(
    int? MemberId,
    string? MemberName,
    string? Username,
    string? Password,
    string? Phone,
    string? Email,
    string? Address,
    int? MemberNumber,
    bool AddHand = false);

public record ImportMembersRequest(string CsvContent, bool SkipDuplicates = true);

public record ImportMembersResultDto(
    int Imported,
    int Skipped,
    IReadOnlyList<string> Errors);

public record GroupMemberRosterItemDto(
    int GroupMemberId,
    int MemberId,
    string MemberName,
    string? Username,
    string? Phone,
    int MemberNumber,
    string? HandLabel,
    string Status,
    DateTime JoinedDate);
