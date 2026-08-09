using FoodPos.Core.Abstractions;
using FoodPos.Core.Entities;
using FoodPos.Core.Session;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FoodPos.App.Views;

public sealed partial class SettingsPage : UserControl
{
    private readonly MainWindow _window;

    public SettingsPage(MainWindow window)
    {
        _window = window;
        InitializeComponent();
        Loaded += SettingsPage_Loaded;
    }

    private async void SettingsPage_Loaded(object sender, RoutedEventArgs e)
    {
        var session = App.Services.GetRequiredService<AppSession>();
        GreetingText.Text = session.CurrentUser is null
            ? "Settings"
            : $"Hello, {session.CurrentUser.DisplayName}";

        using var scope = App.Services.CreateScope();
        var settings = await scope.ServiceProvider.GetRequiredService<ISettingsService>().GetAsync();
        ShopNameBox.Text = settings.ShopName;
        CurrencyBox.Text = settings.CurrencyCode;
        FooterBox.Text = settings.ReceiptFooter;
    }

    private async void SaveButton_Click(object sender, RoutedEventArgs e)
    {
        StatusText.Text = string.Empty;
        SaveButton.IsEnabled = false;

        try
        {
            if (string.IsNullOrWhiteSpace(ShopNameBox.Text))
            {
                StatusText.Text = "Shop name is required.";
                return;
            }

            using var scope = App.Services.CreateScope();
            var service = scope.ServiceProvider.GetRequiredService<ISettingsService>();
            await service.SaveAsync(new ShopSettings
            {
                ShopName = ShopNameBox.Text,
                CurrencyCode = string.IsNullOrWhiteSpace(CurrencyBox.Text) ? "PKR" : CurrencyBox.Text,
                ReceiptFooter = FooterBox.Text ?? string.Empty,
            });

            StatusText.Text = "Saved.";
        }
        catch (Exception ex)
        {
            StatusText.Text = ex.Message;
        }
        finally
        {
            SaveButton.IsEnabled = true;
        }
    }

    private void SignOut_Click(object sender, RoutedEventArgs e)
    {
        App.Services.GetRequiredService<AppSession>().Clear();
        _window.ShowLogin();
    }
}
