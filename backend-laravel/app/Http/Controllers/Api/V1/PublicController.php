<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @group Authentication
 */
class PublicController extends APIController
{
    /**
     * POST /api/v1/auth/register-patient
     * A patient creates an account. A patient record is created with it, so they can use the assistant right away (FR-30).
     */
    public function registerPatient(Request $r)
    {
        $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'contact_no' => 'nullable|string|max:30',
            'birthdate' => 'nullable|date|before:today',
            'address' => 'nullable|string|max:255',
        ]);

        $clinicId = $r->input('clinic_id') ?: DB::table('clinics')->orderBy('clinic_id')->value('clinic_id');

        $ids = DB::transaction(function () use ($r, $clinicId) {
            $uid = Dbx::insert('users', ['name' => $r->name, 'email' => $r->email, 'username' => $r->email, 'password' => Hash::make($r->password), 'role' => 'patient', 'clinic_id' => $clinicId, 'status' => 'active']);
            $pid = Dbx::insert('patients', ['user_id' => $uid, 'clinic_id' => $clinicId, 'full_name' => $r->name, 'email' => $r->email, 'contact_no' => $r->contact_no, 'birthdate' => $r->birthdate, 'address' => $r->address, 'no_show_count' => 0, 'status' => 'active']);

            return [$uid, $pid];
        });
        Dbx::audit((object) ['id' => $ids[0]], 'create', 'users', $ids[0], null, ['role' => 'patient', 'self_registered' => true]);

        return response()->json(['message' => 'Account created. You can log in now.', 'data' => ['userId' => $ids[0], 'patientId' => $ids[1]]], 201);
    }

    /** GET /api/v1/auth/clinics — clinic names for the register screen. */
    public function clinics()
    {
        return response()->json(['data' => DB::table('clinics')->orderBy('clinic_name')->get(['clinic_id', 'clinic_name'])]);
    }
}
