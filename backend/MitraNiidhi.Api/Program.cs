using System.Text.Json.Serialization;
using MitraNiidhi.Application;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Infrastructure;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddApplication();
builder.Services.AddInfrastructure(builder.Configuration);
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.Converters.Add(new JsonStringEnumConverter());
    });
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

builder.Services.AddCors(options =>
{
    var origins = builder.Configuration.GetSection("Cors:Origins").Get<string[]>()
        ??
        [
            "http://localhost:5173",
            "http://127.0.0.1:5173"
        ];

    options.AddPolicy("Frontend", policy =>
        policy.WithOrigins(origins)
            .AllowAnyHeader()
            .AllowAnyMethod());
});

var app = builder.Build();

// Auto-apply missing tables/columns so login and new features don't 500 on older DBs.
using (var scope = app.Services.CreateScope())
{
    try
    {
        var schema = scope.ServiceProvider.GetRequiredService<ISchemaMigrationService>();
        var result = await schema.MigrateAsync();
        var logger = scope.ServiceProvider.GetRequiredService<ILoggerFactory>().CreateLogger("SchemaMigration");
        if (result.AppliedCount > 0)
            logger.LogInformation("Schema migrate applied {Count} change(s): {Items}", result.AppliedCount, string.Join("; ", result.Applied));
        if (result.Errors.Count > 0)
            logger.LogWarning("Schema migrate errors: {Errors}", string.Join("; ", result.Errors));
    }
    catch (Exception ex)
    {
        var logger = scope.ServiceProvider.GetRequiredService<ILoggerFactory>().CreateLogger("SchemaMigration");
        logger.LogError(ex, "Startup schema migration failed.");
    }
}

if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseCors("Frontend");
app.UseAuthentication();
app.UseAuthorization();
app.MapControllers();

app.Run();

public partial class Program;
