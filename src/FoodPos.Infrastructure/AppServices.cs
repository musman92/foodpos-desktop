using FoodPos.Core.Abstractions;
using FoodPos.Core.Session;
using FoodPos.Infrastructure.Data;
using FoodPos.Infrastructure.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace FoodPos.Infrastructure;

public static class AppServices
{
    public const string DefaultConnectionString =
        "Server=.\\SQLEXPRESS;Database=FoodPosOffline;Trusted_Connection=True;TrustServerCertificate=True;MultipleActiveResultSets=True";

    public static string GetDefaultDataDirectory()
    {
        var root = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "FoodPosOffline");
        Directory.CreateDirectory(root);
        return root;
    }

    /// <summary>
    /// Resolution order: FOODPOS_CONNECTION_STRING env → connection.txt → default Express local.
    /// </summary>
    public static string ResolveConnectionString()
    {
        var fromEnv = Environment.GetEnvironmentVariable("FOODPOS_CONNECTION_STRING");
        if (!string.IsNullOrWhiteSpace(fromEnv))
        {
            return fromEnv.Trim();
        }

        var file = Path.Combine(GetDefaultDataDirectory(), "connection.txt");
        if (File.Exists(file))
        {
            var fromFile = File.ReadAllText(file).Trim();
            if (!string.IsNullOrWhiteSpace(fromFile))
            {
                return fromFile;
            }
        }

        return DefaultConnectionString;
    }

    public static ServiceProvider BuildServiceProvider()
    {
        var connectionString = ResolveConnectionString();

        var services = new ServiceCollection();
        services.AddDbContext<FoodPosDbContext>(opt =>
            opt.UseSqlServer(connectionString));
        services.AddSingleton<AppSession>();
        services.AddScoped<IAuthService, AuthService>();
        services.AddScoped<ISettingsService, SettingsService>();

        var provider = services.BuildServiceProvider();

        using (var scope = provider.CreateScope())
        {
            var db = scope.ServiceProvider.GetRequiredService<FoodPosDbContext>();
            try
            {
                DbBootstrap.EnsureCreatedAndSeededAsync(db).GetAwaiter().GetResult();
            }
            catch (Exception ex)
            {
                throw new InvalidOperationException(
                    "Could not connect to SQL Server. Install SQL Server Express (instance .\\SQLEXPRESS) " +
                    "or set FOODPOS_CONNECTION_STRING / %LocalAppData%\\FoodPosOffline\\connection.txt. " +
                    "See SETUP.md. Underlying error: " + ex.Message,
                    ex);
            }
        }

        return provider;
    }
}
