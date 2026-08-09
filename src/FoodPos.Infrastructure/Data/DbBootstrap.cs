using FoodPos.Core.Entities;
using Microsoft.EntityFrameworkCore;

namespace FoodPos.Infrastructure.Data;

public static class DbBootstrap
{
    public const string DefaultAdminUsername = "admin";
    public const string DefaultAdminPassword = "admin123";

    public static async Task EnsureCreatedAndSeededAsync(FoodPosDbContext db, CancellationToken ct = default)
    {
        await db.Database.EnsureCreatedAsync(ct);

        if (!await db.Users.AnyAsync(ct))
        {
            db.Users.Add(new AppUser
            {
                Username = DefaultAdminUsername,
                PasswordHash = BCrypt.Net.BCrypt.HashPassword(DefaultAdminPassword),
                DisplayName = "Administrator",
                IsAdmin = true,
                CreatedAtUtc = DateTime.UtcNow,
            });
        }

        if (!await db.Settings.AnyAsync(ct))
        {
            db.Settings.Add(new ShopSettings
            {
                Id = 1,
                ShopName = "My Restaurant",
                CurrencyCode = "PKR",
                ReceiptFooter = "Thank you!",
                UpdatedAtUtc = DateTime.UtcNow,
            });
        }

        await db.SaveChangesAsync(ct);
    }
}
