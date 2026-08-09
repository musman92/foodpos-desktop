using FoodPos.Core.Abstractions;
using FoodPos.Core.Entities;
using FoodPos.Infrastructure.Data;
using Microsoft.EntityFrameworkCore;

namespace FoodPos.Infrastructure.Services;

public sealed class SettingsService : ISettingsService
{
    private readonly FoodPosDbContext _db;

    public SettingsService(FoodPosDbContext db) => _db = db;

    public async Task<ShopSettings> GetAsync(CancellationToken ct = default)
    {
        var row = await _db.Settings.AsNoTracking().FirstOrDefaultAsync(ct);
        return row ?? new ShopSettings();
    }

    public async Task SaveAsync(ShopSettings settings, CancellationToken ct = default)
    {
        var row = await _db.Settings.FirstOrDefaultAsync(ct);
        if (row is null)
        {
            settings.Id = 1;
            settings.UpdatedAtUtc = DateTime.UtcNow;
            _db.Settings.Add(settings);
        }
        else
        {
            row.ShopName = settings.ShopName.Trim();
            row.CurrencyCode = settings.CurrencyCode.Trim().ToUpperInvariant();
            row.ReceiptFooter = settings.ReceiptFooter.Trim();
            row.UpdatedAtUtc = DateTime.UtcNow;
        }

        await _db.SaveChangesAsync(ct);
    }
}
