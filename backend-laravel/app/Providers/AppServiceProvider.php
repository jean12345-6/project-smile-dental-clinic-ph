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

        // Gates: named yes/no permissions for whole features (WST criterion 3).
        // Policies above protect single records; these protect pages and actions.
        // Least privilege: each gate lists only the roles that need it.
        $admins = ['superadmin', 'clinic_admin'];
        $staff = ['superadmin', 'clinic_admin', 'receptionist', 'dentist'];
        Gate::define('view-reports', fn (User $u) => in_array($u->role, $staff, true));
        Gate::define('view-revenue', fn (User $u) => in_array($u->role, $admins, true));
        Gate::define('view-finance', fn (User $u) => in_array($u->role, $admins, true));
        Gate::define('manage-settings', fn (User $u) => in_array($u->role, $admins, true));
        Gate::define('run-reminders', fn (User $u) => in_array($u->role, $admins, true));
        Gate::define('edit-landing-page', fn (User $u) => in_array($u->role, $admins, true));
    }
}
