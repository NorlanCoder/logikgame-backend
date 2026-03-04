<?php

namespace App\Providers;

use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Route;
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
        Builder::defaultStringLength(191);

        Route::model('round', SessionRound::class);
        Route::model('session', Session::class);
    }
}
