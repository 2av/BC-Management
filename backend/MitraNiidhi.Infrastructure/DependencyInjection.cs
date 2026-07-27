using System.Text;
using Microsoft.AspNetCore.Authentication.JwtBearer;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.IdentityModel.Tokens;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Infrastructure.Identity;
using MitraNiidhi.Infrastructure.Notifications;
using MitraNiidhi.Infrastructure.Persistence;

namespace MitraNiidhi.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        var connectionString = configuration.GetConnectionString("DefaultConnection")
            ?? throw new InvalidOperationException("Connection string 'DefaultConnection' is missing.");

        services.AddHttpContextAccessor();
        services.AddScoped<ICurrentUser, CurrentUserService>();
        services.AddScoped<IJwtTokenService, JwtTokenService>();
        services.AddScoped<IPasswordHasher, BcryptPasswordHasher>();
        services.AddHttpClient("ExpoPush", client =>
        {
            client.Timeout = TimeSpan.FromSeconds(30);
            client.DefaultRequestHeaders.TryAddWithoutValidation("Accept", "application/json");
            client.DefaultRequestHeaders.TryAddWithoutValidation("Accept-Encoding", "gzip, deflate");
        });
        services.AddScoped<IPushNotificationService, ExpoPushNotificationService>();
        services.AddSingleton<MemberPushSaveInterceptor>();

        var serverVersion = new MySqlServerVersion(new Version(8, 0, 36));
        services.AddDbContext<AppDbContext>((sp, options) =>
            options.UseMySql(connectionString, serverVersion)
                .UseSnakeCaseNamingConvention()
                .AddInterceptors(sp.GetRequiredService<MemberPushSaveInterceptor>()));

        services.AddScoped<IAppDbContext>(sp => sp.GetRequiredService<AppDbContext>());
        services.AddScoped<ISchemaMigrationService, SchemaMigrationService>();
        services.AddHostedService<Jobs.SubscriptionExpiryBackgroundService>();

        var jwtKey = configuration["Jwt:Key"] ?? throw new InvalidOperationException("Jwt:Key is missing.");
        services.AddAuthentication(JwtBearerDefaults.AuthenticationScheme)
            .AddJwtBearer(options =>
            {
                options.MapInboundClaims = false;
                options.TokenValidationParameters = new TokenValidationParameters
                {
                    ValidateIssuer = true,
                    ValidateAudience = true,
                    ValidateLifetime = true,
                    ValidateIssuerSigningKey = true,
                    ValidIssuer = configuration["Jwt:Issuer"],
                    ValidAudience = configuration["Jwt:Audience"],
                    IssuerSigningKey = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(jwtKey)),
                    RoleClaimType = "role",
                    NameClaimType = "unique_name"
                };
            });

        services.AddAuthorization();

        return services;
    }
}
