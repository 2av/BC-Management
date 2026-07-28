using MediatR;
using Microsoft.EntityFrameworkCore;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;
using MitraNiidhi.Domain.Entities;
using MitraNiidhi.Domain.Services;

namespace MitraNiidhi.Application.Bidding;

public record GroupMonthChartRowDto(
    int MonthNumber,
    decimal RandomAmount,
    decimal? BoliStartAmount,
    decimal PerMemberIfRandom,
    decimal? PerMemberIfBoli);

public record GroupBcChartDto(
    int GroupId,
    string GroupName,
    int TotalMembers,
    decimal MonthlyContribution,
    decimal TotalMonthlyCollection,
    decimal BoliStepAmount,
    IReadOnlyList<GroupMonthChartRowDto> Months);

public record SaveGroupMonthChartRowRequest(
    int MonthNumber,
    decimal RandomAmount,
    decimal? BoliStartAmount);

public record SaveGroupBcChartRequest(
    decimal BoliStepAmount,
    IReadOnlyList<SaveGroupMonthChartRowRequest> Months);

public record GetGroupBcChartQuery(int GroupId) : IRequest<Result<GroupBcChartDto>>;
public record SaveGroupBcChartCommand(int GroupId, SaveGroupBcChartRequest Request) : IRequest<Result<GroupBcChartDto>>;
public record GenerateDefaultBcChartCommand(int GroupId) : IRequest<Result<GroupBcChartDto>>;

public class GetGroupBcChartQueryHandler(IAppDbContext db)
    : IRequestHandler<GetGroupBcChartQuery, Result<GroupBcChartDto>>
{
    public async Task<Result<GroupBcChartDto>> Handle(GetGroupBcChartQuery request, CancellationToken ct)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == request.GroupId, ct);
        if (group is null) return Result<GroupBcChartDto>.Failure("Group not found.");

        if (BcCalculationService.TrySyncStoredCollection(group))
            await db.SaveChangesAsync(ct);

        await EnsureChartRowsAsync(db, group, ct);
        return Result<GroupBcChartDto>.Success(await MapAsync(db, group, ct));
    }

    internal static async Task EnsureChartRowsAsync(IAppDbContext db, BcGroup group, CancellationToken ct)
    {
        var existing = await db.GroupMonthCharts
            .Where(c => c.GroupId == group.Id)
            .Select(c => c.MonthNumber)
            .ToListAsync(ct);
        if (existing.Count >= group.TotalMembers) return;

        var defaults = BcChartService.BuildDefaultChart(group.TotalMembers, group.MonthlyContribution);
        var have = existing.ToHashSet();
        foreach (var row in defaults)
        {
            if (have.Contains(row.MonthNumber)) continue;
            db.GroupMonthCharts.Add(new GroupMonthChart
            {
                GroupId = group.Id,
                ClientId = group.ClientId,
                MonthNumber = row.MonthNumber,
                RandomAmount = row.RandomAmount,
                BoliStartAmount = row.BoliStartAmount,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow,
            });
        }
        await db.SaveChangesAsync(ct);
    }

    internal static async Task<GroupBcChartDto> MapAsync(IAppDbContext db, BcGroup group, CancellationToken ct)
    {
        var rows = await db.GroupMonthCharts
            .Where(c => c.GroupId == group.Id)
            .OrderBy(c => c.MonthNumber)
            .ToListAsync(ct);

        var n = Math.Max(1, group.TotalMembers);
        return new GroupBcChartDto(
            group.Id,
            group.GroupName,
            group.TotalMembers,
            group.MonthlyContribution,
            group.TotalMonthlyCollection,
            group.BoliStepAmount,
            rows.Select(r => new GroupMonthChartRowDto(
                r.MonthNumber,
                r.RandomAmount,
                r.BoliStartAmount,
                Math.Round(r.RandomAmount / n, 0, MidpointRounding.AwayFromZero),
                r.BoliStartAmount is decimal b
                    ? Math.Round(b / n, 0, MidpointRounding.AwayFromZero)
                    : null)).ToList());
    }
}

public class SaveGroupBcChartCommandHandler(IAppDbContext db)
    : IRequestHandler<SaveGroupBcChartCommand, Result<GroupBcChartDto>>
{
    public async Task<Result<GroupBcChartDto>> Handle(SaveGroupBcChartCommand command, CancellationToken ct)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, ct);
        if (group is null) return Result<GroupBcChartDto>.Failure("Group not found.");

        var req = command.Request;
        if (req.BoliStepAmount <= 0)
            return Result<GroupBcChartDto>.Failure("Per-boli deduction must be greater than 0.");

        group.BoliStepAmount = req.BoliStepAmount;

        var existing = await db.GroupMonthCharts
            .Where(c => c.GroupId == group.Id)
            .ToDictionaryAsync(c => c.MonthNumber, ct);

        foreach (var row in req.Months.OrderBy(m => m.MonthNumber))
        {
            if (row.MonthNumber < 1 || row.MonthNumber > group.TotalMembers)
                return Result<GroupBcChartDto>.Failure($"Invalid month {row.MonthNumber}.");
            if (row.RandomAmount <= 0)
                return Result<GroupBcChartDto>.Failure($"Month {row.MonthNumber}: random amount must be > 0.");
            if (row.RandomAmount > group.TotalMonthlyCollection)
                return Result<GroupBcChartDto>.Failure(
                    $"Month {row.MonthNumber}: random amount cannot exceed collection ({group.TotalMonthlyCollection:0}).");
            if (row.BoliStartAmount is decimal boli)
            {
                if (boli <= 0)
                    return Result<GroupBcChartDto>.Failure($"Month {row.MonthNumber}: boli start must be > 0.");
                if (boli >= group.TotalMonthlyCollection)
                    return Result<GroupBcChartDto>.Failure(
                        $"Month {row.MonthNumber}: boli start must be less than collection.");
            }

            if (existing.TryGetValue(row.MonthNumber, out var entity))
            {
                entity.RandomAmount = row.RandomAmount;
                entity.BoliStartAmount = row.BoliStartAmount;
                entity.UpdatedAt = DateTime.UtcNow;
            }
            else
            {
                db.GroupMonthCharts.Add(new GroupMonthChart
                {
                    GroupId = group.Id,
                    ClientId = group.ClientId,
                    MonthNumber = row.MonthNumber,
                    RandomAmount = row.RandomAmount,
                    BoliStartAmount = row.BoliStartAmount,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow,
                });
            }
        }

        await db.SaveChangesAsync(ct);
        return Result<GroupBcChartDto>.Success(await GetGroupBcChartQueryHandler.MapAsync(db, group, ct));
    }
}

public class GenerateDefaultBcChartCommandHandler(IAppDbContext db)
    : IRequestHandler<GenerateDefaultBcChartCommand, Result<GroupBcChartDto>>
{
    public async Task<Result<GroupBcChartDto>> Handle(GenerateDefaultBcChartCommand command, CancellationToken ct)
    {
        var group = await db.BcGroups.FirstOrDefaultAsync(g => g.Id == command.GroupId, ct);
        if (group is null) return Result<GroupBcChartDto>.Failure("Group not found.");

        if (group.BoliStepAmount <= 0) group.BoliStepAmount = 1000;

        var defaults = BcChartService.BuildDefaultChart(group.TotalMembers, group.MonthlyContribution);
        var existing = await db.GroupMonthCharts
            .Where(c => c.GroupId == group.Id)
            .ToDictionaryAsync(c => c.MonthNumber, ct);

        foreach (var row in defaults)
        {
            if (existing.TryGetValue(row.MonthNumber, out var entity))
            {
                entity.RandomAmount = row.RandomAmount;
                entity.BoliStartAmount = row.BoliStartAmount;
                entity.UpdatedAt = DateTime.UtcNow;
            }
            else
            {
                db.GroupMonthCharts.Add(new GroupMonthChart
                {
                    GroupId = group.Id,
                    ClientId = group.ClientId,
                    MonthNumber = row.MonthNumber,
                    RandomAmount = row.RandomAmount,
                    BoliStartAmount = row.BoliStartAmount,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow,
                });
            }
        }

        await db.SaveChangesAsync(ct);
        return Result<GroupBcChartDto>.Success(await GetGroupBcChartQueryHandler.MapAsync(db, group, ct));
    }
}
