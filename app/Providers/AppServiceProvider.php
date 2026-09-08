<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admins bypass individual permission checks. Everyone else is
        // evaluated against the named abilities in App\Support\Permissions.
        Gate::before(fn ($user) => $user->hasRole('admin') ? true : null);

        // Fail loudly in development instead of silently returning null.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(6));

        Vite::prefetch(concurrency: 3);
    }
}
