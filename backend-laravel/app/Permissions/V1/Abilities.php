<?php

namespace App\Permissions\V1;

use App\Models\User;

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

    // User management abilities (admins only)
    public const CreateUser = 'user:create';
    public const UpdateUser = 'user:update';
    public const ReplaceUser = 'user:replace';
    public const DeleteUser = 'user:delete';

    /**
     * The abilities written into the JWT at login, based on the user's role.
     */
    public static function getAbilities(User $user): array
    {
        return match ($user->role) {
            'superadmin', 'clinic_admin' => [
                self::CreateAppointment,
                self::UpdateAppointment,
                self::ReplaceAppointment,
                self::DeleteAppointment,
                self::CreateUser,
                self::UpdateUser,
                self::ReplaceUser,
                self::DeleteUser,
            ],
            'receptionist' => [
                self::CreateAppointment,
                self::UpdateAppointment,
                self::ReplaceAppointment,
                self::DeleteAppointment,
            ],
            'dentist' => [
                self::UpdateAppointment,
            ],
            'patient' => [
                self::CreateOwnAppointment,
                self::UpdateOwnAppointment,
                self::DeleteOwnAppointment,
            ],
            default => [],
        };
    }
}
