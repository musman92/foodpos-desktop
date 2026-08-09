namespace FoodPos.Core.Entities;

public class ShopSettings
{
    public int Id { get; set; } = 1;
    public string ShopName { get; set; } = "My Restaurant";
    public string CurrencyCode { get; set; } = "PKR";
    public string ReceiptFooter { get; set; } = "Thank you!";
    public DateTime UpdatedAtUtc { get; set; } = DateTime.UtcNow;
}
