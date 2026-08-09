<?php

namespace App\Providers;

use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Observers\MoneySourceFundMovementObserver;
use App\Observers\MoneySourceObserver;
use App\Observers\OrderObserver;
use App\Observers\PurchaseObserver;
use App\Observers\TransactionObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url');
        if (config('app.env') === 'production' || str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        MoneySource::observe(MoneySourceObserver::class);
        MoneySourceFundMovement::observe(MoneySourceFundMovementObserver::class);
        Transaction::observe(TransactionObserver::class);
        Order::observe(OrderObserver::class);
        Purchase::observe(PurchaseObserver::class);
    }
}
