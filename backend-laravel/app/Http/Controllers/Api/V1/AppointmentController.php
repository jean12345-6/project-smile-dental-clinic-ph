<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\AppointmentFilter;
use App\Http\Requests\Api\V1\ReplaceAppointmentRequest;
use App\Http\Requests\Api\V1\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\UpdateAppointmentRequest;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

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
     * POST /api/v1/appointments
     * Validates the body with StoreAppointmentRequest, then books the appointment.
     * Laravel answers 201 Created automatically for a newly created resource.
     */
    public function store(StoreAppointmentRequest $request)
    {
        // mappedAttributes() converts the nested JSON into database columns
        $appointment = Appointment::create($request->mappedAttributes());

        // refresh() loads the database defaults (status, risk_flag, booked_via) into the response
        return new AppointmentResource($appointment->refresh());
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

    /**
     * DELETE /api/v1/appointments/{appointment}
     * Deletes the appointment. A missing appointment never reaches this method:
     * Laravel answers 404 for us.
     */
    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return $this->ok('Appointment successfully deleted');
    }

    /**
     * PUT /api/v1/appointments/{appointment}
     * Replaces the whole appointment: every field must be sent (see ReplaceAppointmentRequest).
     */
    public function replace(ReplaceAppointmentRequest $request, Appointment $appointment)
    {
        $appointment->update($request->mappedAttributes());

        return new AppointmentResource($appointment);
    }

    /**
     * PATCH /api/v1/appointments/{appointment}
     * Updates only the fields that were sent (see UpdateAppointmentRequest).
     * checkInTime is allowed here only, so the front desk can check a patient in.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        // mappedAttributes() returns only the keys present in the request
        $appointment->update(
            $request->mappedAttributes(['data.attributes.checkInTime' => 'check_in_time'])
        );

        return new AppointmentResource($appointment);
    }
}