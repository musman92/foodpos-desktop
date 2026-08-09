using FoodPos.Core.Abstractions;
using FoodPos.Core.Session;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FoodPos.App.Views;

public sealed partial class LoginPage : UserControl
{
    private readonly MainWindow _window;

    public LoginPage(MainWindow window)
    {
        _window = window;
        InitializeComponent();
    }

    private async void LoginButton_Click(object sender, RoutedEventArgs e)
    {
        ErrorText.Visibility = Visibility.Collapsed;
        LoginButton.IsEnabled = false;

        try
        {
            using var scope = App.Services.CreateScope();
            var auth = scope.ServiceProvider.GetRequiredService<IAuthService>();
            var session = App.Services.GetRequiredService<AppSession>();

            var user = await auth.SignInAsync(UsernameBox.Text, PasswordBox.Password);
            if (user is null)
            {
                ErrorText.Text = "Invalid username or password.";
                ErrorText.Visibility = Visibility.Visible;
                return;
            }

            session.SetUser(user);
            _window.ShowSettings();
        }
        catch (Exception ex)
        {
            ErrorText.Text = ex.Message;
            ErrorText.Visibility = Visibility.Visible;
        }
        finally
        {
            LoginButton.IsEnabled = true;
        }
    }
}
