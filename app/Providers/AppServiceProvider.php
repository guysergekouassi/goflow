<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

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
        Paginator::useBootstrapFive();

        // Rediriger les utilisateurs déjà authentifiés vers leur tableau de bord respectif
        // au lieu de '/' (landing page) afin d'éviter tout rejet ou boucle de redirection
        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(function ($request) {
            return \App\Http\Controllers\Auth\LoginController::getDashboardUrl();
        });
    }
}
