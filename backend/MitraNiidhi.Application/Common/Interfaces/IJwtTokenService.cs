using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Common.Interfaces;

public interface IJwtTokenService
{
    string CreateAccessToken(int userId, string username, string fullName, UserRole role, int? clientId);
}
