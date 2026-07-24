using System.Globalization;
using System.Text;
using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;

namespace MitraNiidhi.Application.Members;

public record ImportGroupMembersCommand(int GroupId, ImportMembersRequest Request)
    : IRequest<Result<ImportMembersResultDto>>;

public class ImportGroupMembersCommandHandler(IAppDbContext db, IPasswordHasher passwordHasher)
    : IRequestHandler<ImportGroupMembersCommand, Result<ImportMembersResultDto>>
{
    public async Task<Result<ImportMembersResultDto>> Handle(
        ImportGroupMembersCommand command,
        CancellationToken cancellationToken)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, cancellationToken);
        if (group is null)
            return Result<ImportMembersResultDto>.Failure("Group not found.");

        if (group.Status == Domain.Enums.GroupStatus.Completed)
            return Result<ImportMembersResultDto>.Failure("This group is completed — members cannot be imported.");

        var lines = command.Request.CsvContent
            .Replace("\r\n", "\n")
            .Split('\n', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries);

        if (lines.Length < 2)
            return Result<ImportMembersResultDto>.Failure("CSV must include a header row and at least one data row.");

        // Expected: member_name,member_number,username,phone,email,address
        var imported = 0;
        var skipped = 0;
        var errors = new List<string>();

        for (var i = 1; i < lines.Length; i++)
        {
            var rowNum = i + 1;
            var cols = ParseCsvLine(lines[i]);
            if (cols.Count == 0) continue;

            var name = cols.ElementAtOrDefault(0)?.Trim() ?? "";
            if (string.IsNullOrWhiteSpace(name))
            {
                errors.Add($"Row {rowNum}: member_name is required.");
                skipped++;
                continue;
            }

            int? memberNumber = null;
            if (!string.IsNullOrWhiteSpace(cols.ElementAtOrDefault(1)) &&
                int.TryParse(cols[1], NumberStyles.Integer, CultureInfo.InvariantCulture, out var n))
                memberNumber = n;

            var usernameRaw = cols.ElementAtOrDefault(2)?.Trim();
            var phone = EmptyToNull(cols.ElementAtOrDefault(3));
            var email = EmptyToNull(cols.ElementAtOrDefault(4));
            var address = EmptyToNull(cols.ElementAtOrDefault(5));

            try
            {
                string username;
                if (string.IsNullOrWhiteSpace(usernameRaw))
                {
                    username = await MemberUsernameHelper.EnsureUniqueUsernameAsync(db, name, null, cancellationToken);
                }
                else
                {
                    username = usernameRaw.ToLowerInvariant();
                    var taken = await db.Members.AnyAsync(m => m.Username == username, cancellationToken);
                    if (taken)
                    {
                        if (command.Request.SkipDuplicates)
                        {
                            username = await MemberUsernameHelper.EnsureUniqueUsernameAsync(db, name, null, cancellationToken);
                        }
                        else
                        {
                            errors.Add($"Row {rowNum}: username '{username}' already exists.");
                            skipped++;
                            continue;
                        }
                    }
                }

                // Reuse existing member by exact name + username match when possible
                var member = await db.Members.FirstOrDefaultAsync(
                    m => m.Username == username || m.MemberName == name,
                    cancellationToken);

                if (member is null)
                {
                    member = new Member
                    {
                        MemberName = name,
                        Username = username,
                        PasswordHash = passwordHasher.Hash("member123"),
                        Phone = phone,
                        Email = email,
                        Address = address,
                        Status = "active"
                    };
                    db.Members.Add(member);
                    await db.SaveChangesAsync(cancellationToken);
                }

                var assign = await AssignInternal.AssignAsync(
                    db, command.GroupId, member.Id, memberNumber, cancellationToken);

                if (!assign.Succeeded)
                {
                    if (command.Request.SkipDuplicates &&
                        assign.Error?.Contains("already assigned", StringComparison.OrdinalIgnoreCase) == true)
                    {
                        skipped++;
                        continue;
                    }

                    errors.Add($"Row {rowNum}: {assign.Error}");
                    skipped++;
                    continue;
                }

                imported++;
            }
            catch (Exception ex)
            {
                errors.Add($"Row {rowNum}: {ex.Message}");
                skipped++;
            }
        }

        return Result<ImportMembersResultDto>.Success(new ImportMembersResultDto(imported, skipped, errors));
    }

    private static string? EmptyToNull(string? value) =>
        string.IsNullOrWhiteSpace(value) ? null : value.Trim();

    private static List<string> ParseCsvLine(string line)
    {
        var result = new List<string>();
        var sb = new StringBuilder();
        var inQuotes = false;
        for (var i = 0; i < line.Length; i++)
        {
            var c = line[i];
            if (c == '"')
            {
                if (inQuotes && i + 1 < line.Length && line[i + 1] == '"')
                {
                    sb.Append('"');
                    i++;
                }
                else inQuotes = !inQuotes;
            }
            else if (c == ',' && !inQuotes)
            {
                result.Add(sb.ToString());
                sb.Clear();
            }
            else sb.Append(c);
        }
        result.Add(sb.ToString());
        return result;
    }
}
