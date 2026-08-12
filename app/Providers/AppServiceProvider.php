<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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
        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $user) {
                return route('login');
            }

            $routeName = $user->loadMissing('roles')->portalRouteName();

            return $routeName ? route($routeName) : route('login');
        });

        View::composer('layouts.portal', function ($view): void {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $user) {
                return;
            }

            $view->with([
                'portalNotifications' => $user->appNotifications()
                    ->latest('notification_id')
                    ->limit(8)
                    ->get(),
                'portalUnreadNotifications' => $user->appNotifications()
                    ->whereNull('read_at')
                    ->count(),
            ]);
        });
    }
}
