using FoodPos.Core.Entities;
using Microsoft.EntityFrameworkCore;

namespace FoodPos.Infrastructure.Data;

public sealed class FoodPosDbContext : DbContext
{
    public FoodPosDbContext(DbContextOptions<FoodPosDbContext> options)
        : base(options)
    {
    }

    public DbSet<AppUser> Users => Set<AppUser>();
    public DbSet<ShopSettings> Settings => Set<ShopSettings>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<AppUser>(e =>
        {
            e.HasIndex(x => x.Username).IsUnique();
            e.Property(x => x.Username).HasMaxLength(64);
            e.Property(x => x.DisplayName).HasMaxLength(128);
        });

        modelBuilder.Entity<ShopSettings>(e =>
        {
            e.Property(x => x.ShopName).HasMaxLength(200);
            e.Property(x => x.CurrencyCode).HasMaxLength(8);
            e.Property(x => x.ReceiptFooter).HasMaxLength(500);
        });
    }
}
