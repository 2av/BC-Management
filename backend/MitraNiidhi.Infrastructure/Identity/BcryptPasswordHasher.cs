using MitraNiidhi.Application.Common.Interfaces;

namespace MitraNiidhi.Infrastructure.Identity;

public class BcryptPasswordHasher : IPasswordHasher
{
    public string Hash(string password) => BCrypt.Net.BCrypt.HashPassword(password);

    public bool Verify(string password, string passwordHash)
    {
        try
        {
            // Supports PHP password_hash / ASP.NET bcrypt hashes
            return BCrypt.Net.BCrypt.Verify(password, passwordHash);
        }
        catch
        {
            return false;
        }
    }
}
