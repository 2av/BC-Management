using MediatR;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Settings;

public record CheckSchemaQuery : IRequest<Result<SchemaCheckResultDto>>;
public record MigrateSchemaCommand : IRequest<Result<SchemaMigrateResultDto>>;

public class CheckSchemaQueryHandler(ISchemaMigrationService schema)
    : IRequestHandler<CheckSchemaQuery, Result<SchemaCheckResultDto>>
{
    public async Task<Result<SchemaCheckResultDto>> Handle(CheckSchemaQuery request, CancellationToken ct)
        => Result<SchemaCheckResultDto>.Success(await schema.CheckAsync(ct));
}

public class MigrateSchemaCommandHandler(ISchemaMigrationService schema)
    : IRequestHandler<MigrateSchemaCommand, Result<SchemaMigrateResultDto>>
{
    public async Task<Result<SchemaMigrateResultDto>> Handle(MigrateSchemaCommand request, CancellationToken ct)
        => Result<SchemaMigrateResultDto>.Success(await schema.MigrateAsync(ct));
}
