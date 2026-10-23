<?php

namespace App\Permissions\V1;

use App\Models\User;

/**
 * Every ability name in one place, plus the rule that decides who receives which.
 * Format: resource:action. "own" means "only for things I own".
 */
final class Abilities
{
    // Appointment abilities
    public const CreateAppointment = 'appointment:create';
    public const CreateOwnAppointment = 'appointment:own:create';
    public const UpdateAppointment = 'appointment:update';
    public const UpdateOwnAppointment = 'appointment:own:update';
    public const ReplaceAppointment = 'appointment:replace';
    public const DeleteAppointment = 'appointment:delete';
    public const DeleteOwnAppointment = 'appointment:own:delete';

    /**
     * Returns the list of abilities a user receives, based on the role.
     * The list is written into the JWT at login, so the policies can read it
     * without querying the database.
     */
    public static function getAbilities(User $user): array
    {
        return match ($user->role) {
            // Clinic staff may act on any appointment
            'superadmin', 'clinic_admin', 'receptionist' => [
                self::CreateAppointment,
                self::UpdateAppointment,
                self::ReplaceAppointment,
                self::DeleteAppointment,
            ],

            // Dentists may update appointments (check-in, complete, notes)
            'dentist' => [
                self::UpdateAppointment,
            ],

            // Patients may only act on their own appointments
            'patient' => [
                self::CreateOwnAppointment,
                self::UpdateOwnAppointment,
                self::DeleteOwnAppointment,
            ],

            default => [],
        };
    }
}