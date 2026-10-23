<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

class AppointmentController extends APIController
{
    /**
     * GET /api/v1/appointments
     * Add ?include=patient to load each appointment's patient in one extra query.
     */
    public function index()
    {
        if ($this->include('patient')) {
            return AppointmentResource::collection(Appointment::with('patient')->paginate());
        }

        return AppointmentResource::collection(Appointment::paginate());
    }

    /**
     * GET /api/v1/appointments/{appointment}
     * Add ?include=patient to load the patient of this appointment.
     */
    public function show(Appointment $appointment)
    {
        if ($this->include('patient')) {
            return new AppointmentResource($appointment->load('patient'));
        }

        return new AppointmentResource($appointment);
    }
}