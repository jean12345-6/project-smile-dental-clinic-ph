<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\PatientFilter;
use App\Http\Resources\V1\PatientResource;
use App\Models\Patient;
use App\Support\Dbx;
use Illuminate\Http\Request;

/**
 * Read-only view of the patients who have at least one appointment.
 */
/**
 * @group Patients
 *
 * Read patients (list and show).
 */
class PatientsController extends APIController
{
    /**
     * GET /api/v1/patients
     * Filters and sorting come from the URL (?fullName=*maria*, ?sort=-createdAt ...).
     * Add ?include=appointments to load each patient's appointments in one extra query.
     *
     * Staff only. A patient must never be able to list other patients: the clinic
     * scope limits rows to the clinic, not to the person, so without this check a
     * signed-in patient could read every other patient's name, email and contact
     * number. Patients use the /my/* endpoints for their own record instead.
     */
    public function index(Request $request, PatientFilter $filters)
    {
        if ($request->user()?->role === 'patient') {
            return Dbx::err('You are not allowed to view this.', 403);
        }

        return PatientResource::collection(
            Patient::has('appointments')->filter($filters)->paginate()->withQueryString()
        );
    }

    /**
     * GET /api/v1/patients/{patient}
     * Add ?include=appointments to load this patient's appointments.
     *
     * A patient may read only their own record.
     */
    public function show(Request $request, Patient $patient)
    {
        $user = $request->user();

        if ($user?->role === 'patient'
            && (int) ($user->patient?->patient_id ?? -1) !== (int) $patient->patient_id) {
            return Dbx::err('You are not allowed to view this.', 403);
        }

        if ($this->include('appointments')) {
            return new PatientResource($patient->load('appointments'));
        }

        return new PatientResource($patient);
    }
}
