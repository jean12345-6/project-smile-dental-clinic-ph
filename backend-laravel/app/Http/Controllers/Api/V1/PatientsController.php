<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PatientResource;
use App\Models\Patient;

/**
 * Read-only view of the patients who have at least one appointment.
 */
class PatientsController extends Controller
{
    /**
     * GET /api/v1/patients
     * Lists only patients who have at least one appointment, 15 per page.
     */
    public function index()
    {
        return PatientResource::collection(Patient::has('appointments')->paginate());
    }

    /**
     * GET /api/v1/patients/{patient}
     * Returns one patient (route model binding loads the Patient).
     */
    public function show(Patient $patient)
    {
        return new PatientResource($patient);
    }
}