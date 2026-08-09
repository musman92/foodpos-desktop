using FoodPos.App.Views;
using FoodPos.Core.Session;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.UI.Xaml;

namespace FoodPos.App;

public sealed partial class MainWindow : Window
{
    public MainWindow()
    {
        InitializeComponent();
        ExtendsContentIntoTitleBar = false;
        NavigateToStart();
    }

    public void NavigateToStart()
    {
        var session = App.Services.GetRequiredService<AppSession>();
        RootGrid.Children.Clear();
        UIElement page = session.IsSignedIn
            ? new SettingsPage(this)
            : new LoginPage(this);
        RootGrid.Children.Add(page);
    }

    public void ShowSettings()
    {
        RootGrid.Children.Clear();
        RootGrid.Children.Add(new SettingsPage(this));
    }

    public void ShowLogin()
    {
        RootGrid.Children.Clear();
        RootGrid.Children.Add(new LoginPage(this));
    }
}
