using FoodPos.Core.Abstractions;
using FoodPos.Core.Entities;
using FoodPos.Infrastructure.Data;
using Microsoft.EntityFrameworkCore;

namespace FoodPos.Infrastructure.Services;

public sealed class AuthService : IAuthService
{
    private readonly FoodPosDbContext _db;

    public AuthService(FoodPosDbContext db) => _db = db;

    public async Task<AppUser?> SignInAsync(string username, string password, CancellationToken ct = default)
    {
        var user = await _db.Users
            .AsNoTracking()
            .FirstOrDefaultAsync(u => u.Username == username.Trim(), ct);

        if (user is null)
        {
            return null;
        }

        return BCrypt.Net.BCrypt.Verify(password, user.PasswordHash) ? user : null;
    }
}
