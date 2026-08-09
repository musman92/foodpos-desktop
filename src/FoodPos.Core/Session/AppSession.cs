using FoodPos.Core.Entities;

namespace FoodPos.Core.Session;

/// <summary>Simple in-memory session for MVP (cleared on process exit).</summary>
public sealed class AppSession
{
    public AppUser? CurrentUser { get; private set; }

    public bool IsSignedIn => CurrentUser is not null;

    public void SetUser(AppUser user) => CurrentUser = user;

    public void Clear() => CurrentUser = null;
}
