using System.Security.Claims;
using Microsoft.AspNetCore.Http;
using MitraNiidhi.Application.Common.Interfaces;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Infrastructure.Identity;

public class CurrentUserService(IHttpContextAccessor httpContextAccessor) : ICurrentUser
{
    private ClaimsPrincipal? User => httpContextAccessor.HttpContext?.User;

    public bool IsAuthenticated => User?.Identity?.IsAuthenticated == true;

    public int? UserId
    {
        get
        {
            var value = User?.FindFirstValue(ClaimTypes.NameIdentifier)
                ?? User?.FindFirstValue(JwtRegisteredClaimNamesSub)
                ?? User?.FindFirstValue("sub");
            return int.TryParse(value, out var id) ? id : null;
        }
    }

    public string? Username =>
        User?.FindFirstValue(ClaimTypes.Name)
        ?? User?.FindFirstValue("unique_name")
        ?? User?.Identity?.Name;

    public UserRole? Role
    {
        get
        {
            var value = User?.FindFirstValue(ClaimTypes.Role) ?? User?.FindFirstValue("role");
            return Enum.TryParse<UserRole>(value, out var role) ? role : null;
        }
    }

    public int? ClientId
    {
        get
        {
            var value = User?.FindFirstValue("client_id");
            return int.TryParse(value, out var id) ? id : null;
        }
    }

    public string? DisplayName => User?.FindFirstValue("full_name") ?? User?.Identity?.Name;

    public bool IsSuperAdmin => Role == UserRole.SuperAdmin;

    private const string JwtRegisteredClaimNamesSub = "sub";
}
