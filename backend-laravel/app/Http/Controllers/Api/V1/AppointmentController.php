<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;
use App\Http\Filters\V1\AppointmentFilter;

class AppointmentController extends APIController
{
        /**
     * GET /api/v1/appointments
     * Returns a paginated list, narrowed by the filters in the URL
     * (?status=confirmed, ?dentistId=1, ?startTime=2026-10-20, ?include=patient ...).
     */
    public function index(AppointmentFilter $filters)
    {
        return AppointmentResource::collection(
            Appointment::filter($filters)->paginate()->withQueryString()
        );
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