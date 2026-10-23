<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

class AppointmentController extends Controller
{
    /**
     * GET /api/v1/appointments
     * Returns a paginated list (15 per page). Each appointment is transformed by AppointmentResource.
     */
    public function index()
    {
        return AppointmentResource::collection(Appointment::paginate());
    }

    /**
     * GET /api/v1/appointments/{appointment}
     * Returns one appointment. Laravel loads it from the URL (route model binding)
     * and answers 404 by itself when it does not exist.
     */
    public function show(Appointment $appointment)
    {
        return new AppointmentResource($appointment);
    }
}