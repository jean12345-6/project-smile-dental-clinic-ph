<?php

namespace App\Policies\V1;

use App\Models\Appointment;
use App\Models\User;

/**
 * Authorization rules for appointments. Each method returns true (allowed) or false (denied).
 */
class AppointmentPolicy
{
    /**
     * May this user update this appointment? Only the patient who owns it (for now).
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->patient?->user_id;
    }
}