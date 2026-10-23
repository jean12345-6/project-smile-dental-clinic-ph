<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\AppointmentFilter;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;

/**
 * Appointments that belong to one patient: /api/v1/patients/{patient}/appointments
 */
class PatientAppointmentsController extends APIController
{
    /**
     * GET /api/v1/patients/{patient}/appointments
     * Lists only this patient's appointments. All AppointmentFilter parameters work here too.
     */
    public function index(Patient $patient, AppointmentFilter $filters)
    {
        return AppointmentResource::collection(
            Appointment::where('patient_id', $patient->patient_id)
                ->filter($filters)
                ->paginate()
                ->withQueryString()
        );
    }
}