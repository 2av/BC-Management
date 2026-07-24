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

// Surface unhandled exceptions as JSON so the UI can show the real error.
app.Use(async (context, next) =>
{
    try
    {
        await next();
    }
    catch (Exception ex)
    {
        var logger = context.RequestServices.GetRequiredService<ILoggerFactory>()
            .CreateLogger("UnhandledException");
        logger.LogError(ex, "Unhandled exception on {Method} {Path}", context.Request.Method, context.Request.Path);

        if (context.Response.HasStarted)
            throw;

        context.Response.Clear();
        context.Response.StatusCode = StatusCodes.Status500InternalServerError;
        context.Response.ContentType = "application/json";
        await context.Response.WriteAsJsonAsync(new
        {
            message = ex.Message,
            exception = ex.GetType().Name,
            detail = ex.InnerException?.Message
        });
    }
});

app.UseAuthentication();
app.UseAuthorization();
app.MapControllers();

app.MapGet("/api/health", () => Results.Ok(new
{
    status = "ok",
    time = DateTime.UtcNow
}));

app.Run();

public partial class Program;
