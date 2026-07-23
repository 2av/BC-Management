using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Common.Interfaces;

public interface ICurrentUser
{
    bool IsAuthenticated { get; }
    int? UserId { get; }
    string? Username { get; }
    UserRole? Role { get; }
    int? ClientId { get; }
    string? DisplayName { get; }
    bool IsSuperAdmin { get; }
}
