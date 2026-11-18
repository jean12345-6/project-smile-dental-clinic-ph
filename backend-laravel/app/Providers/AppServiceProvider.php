<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Policies\V1\AppointmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Policies\V1\UserPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Clinic isolation must be registered here, not from a route file:
        // route:cache stops route files from being evaluated in production.
        \App\Support\Boot::register();
        // Appointment models are protected by AppointmentPolicy
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
