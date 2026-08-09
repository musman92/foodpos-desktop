using FoodPos.Core.Entities;

namespace FoodPos.Core.Abstractions;

public interface ISettingsService
{
    Task<ShopSettings> GetAsync(CancellationToken ct = default);
    Task SaveAsync(ShopSettings settings, CancellationToken ct = default);
}
