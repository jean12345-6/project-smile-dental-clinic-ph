<?php

namespace App\Policies\V1;

use App\Models\Appointment;
use App\Models\User;
use App\Permissions\V1\Abilities;

/**
 * Authorization rules for appointments. Each method returns true (allowed) or false (denied).
 * The rules read the abilities stored in the user's JWT.
 */
class AppointmentPolicy
{
    /**
     * Does this appointment belong to this user (through their patient record)?
     */
    private function owns(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->patient?->user_id;
    }

    /**
     * May this user create an appointment? Needs the broad or the "own" create ability.
     */
    public function store(User $user): bool
    {
        return $user->tokenCan(Abilities::CreateAppointment)
            || $user->tokenCan(Abilities::CreateOwnAppointment);
    }

    /**
     * May this user update this appointment (PATCH)?
     */
    public function update(User $user, Appointment $appointment): bool
    {
        // Broad ability: any appointment
        if ($user->tokenCan(Abilities::UpdateAppointment)) {
            return true;
        }

        // "Own" ability: only an appointment the user owns
        if ($user->tokenCan(Abilities::UpdateOwnAppointment)) {
            return $this->owns($user, $appointment);
        }

        return false;
    }

    /**
     * May this user replace this appointment (PUT)? Staff only.
     */
    public function replace(User $user, Appointment $appointment): bool
    {
        return $user->tokenCan(Abilities::ReplaceAppointment);
    }

    /**
     * May this user delete this appointment?
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        // Broad ability: any appointment
        if ($user->tokenCan(Abilities::DeleteAppointment)) {
            return true;
        }

        // "Own" ability: only an appointment the user owns
        if ($user->tokenCan(Abilities::DeleteOwnAppointment)) {
            return $this->owns($user, $appointment);
        }

        return false;
    }
}