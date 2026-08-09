using FoodPos.Core.Entities;

namespace FoodPos.Core.Abstractions;

public interface IAuthService
{
    Task<AppUser?> SignInAsync(string username, string password, CancellationToken ct = default);
}
