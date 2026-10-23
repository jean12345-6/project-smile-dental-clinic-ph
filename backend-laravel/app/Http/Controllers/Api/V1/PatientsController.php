<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\PatientFilter;
use App\Http\Resources\V1\PatientResource;
use App\Models\Patient;

/**
 * Read-only view of the patients who have at least one appointment.
 */
class PatientsController extends APIController
{
    /**
     * GET /api/v1/patients
     * Filters and sorting come from the URL (?fullName=*maria*, ?sort=-createdAt ...).
     * Add ?include=appointments to load each patient's appointments in one extra query.
     */
    public function index(PatientFilter $filters)
    {
        return PatientResource::collection(
            Patient::has('appointments')->filter($filters)->paginate()->withQueryString()
        );
    }

    /**
     * GET /api/v1/patients/{patient}
     * Add ?include=appointments to load this patient's appointments.
     */
    public function show(Patient $patient)
    {
        if ($this->include('appointments')) {
            return new PatientResource($patient->load('appointments'));
        }

        return new PatientResource($patient);
    }
}