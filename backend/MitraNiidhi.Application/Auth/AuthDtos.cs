using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Application.Auth;

public record LoginRequest(string Username, string Password, UserRole Portal);

public record AuthUserDto(
    int Id,
    string Username,
    string FullName,
    UserRole Role,
    int? ClientId,
    string AccessToken);
