using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Common.Interfaces;

public record SchemaIssueDto(
    string Kind,
    string ObjectName,
    string Detail,
    bool CanAutoFix);

public record SchemaCheckResultDto(
    bool IsUpToDate,
    IReadOnlyList<SchemaIssueDto> Issues,
    IReadOnlyList<string> Notes);

public record SchemaMigrateResultDto(
    bool Succeeded,
    int AppliedCount,
    IReadOnlyList<string> Applied,
    IReadOnlyList<string> Skipped,
    IReadOnlyList<string> Errors);

public interface ISchemaMigrationService
{
    Task<SchemaCheckResultDto> CheckAsync(CancellationToken cancellationToken = default);
    Task<SchemaMigrateResultDto> MigrateAsync(CancellationToken cancellationToken = default);
}
