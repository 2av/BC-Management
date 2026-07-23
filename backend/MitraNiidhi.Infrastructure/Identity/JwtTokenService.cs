using System.IdentityModel.Tokens.Jwt;
using System.Security.Claims;
using System.Text;
using Microsoft.Extensions.Configuration;
using Microsoft.IdentityModel.Tokens;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Infrastructure.Identity;

public class JwtTokenService(IConfiguration configuration) : IJwtTokenService
{
    public string CreateAccessToken(int userId, string username, string fullName, UserRole role, int? clientId)
    {
        var key = configuration["Jwt:Key"] ?? throw new InvalidOperationException("Jwt:Key is not configured.");
        var issuer = configuration["Jwt:Issuer"] ?? "MitraNiidhi";
        var audience = configuration["Jwt:Audience"] ?? "MitraNiidhi.App";
        var expiresMinutes = int.TryParse(configuration["Jwt:ExpiresMinutes"], out var m) ? m : 480;

        var claims = new List<Claim>
        {
            new(JwtRegisteredClaimNames.Sub, userId.ToString()),
            new(JwtRegisteredClaimNames.UniqueName, username),
            new("full_name", fullName),
            new(ClaimTypes.Role, role.ToString()),
            new("role", role.ToString())
        };

        if (clientId.HasValue)
            claims.Add(new Claim("client_id", clientId.Value.ToString()));

        var credentials = new SigningCredentials(
            new SymmetricSecurityKey(Encoding.UTF8.GetBytes(key)),
            SecurityAlgorithms.HmacSha256);

        var token = new JwtSecurityToken(
            issuer: issuer,
            audience: audience,
            claims: claims,
            expires: DateTime.UtcNow.AddMinutes(expiresMinutes),
            signingCredentials: credentials);

        return new JwtSecurityTokenHandler().WriteToken(token);
    }
}
