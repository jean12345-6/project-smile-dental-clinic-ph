<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\AppointmentFilter;
use App\Http\Requests\Api\V1\ReplaceAppointmentRequest;
use App\Http\Requests\Api\V1\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\UpdateAppointmentRequest;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;

/**
 * Appointments that belong to one patient: /api/v1/patients/{patient}/appointments
 */
/**
 * @group Patient Appointments
 *
 * The appointments of one patient. The patient id comes from the URL.
 */
class PatientAppointmentsController extends APIController
{
    /**
     * GET /api/v1/patients/{patient}/appointments
     * Lists only this patient's appointments. All AppointmentFilter parameters work here too.
     */
    public function index(Patient $patient, AppointmentFilter $filters)
    {
        // A patient may list only their own appointments, not another patient's by changing the id
        $user = request()->user();
        if ($user?->role === 'patient' && (int) $patient->user_id !== (int) $user->id) {
            return $this->notAuthorized('You are not allowed to view this.');
        }
        return AppointmentResource::collection(
            Appointment::where('patient_id', $patient->patient_id)
                ->filter($filters)
                ->paginate()
                ->withQueryString()
        );
    }

    /**
     * POST /api/v1/patients/{patient}/appointments
     * Creates an appointment for the patient named in the URL.
     */
    public function store(StoreAppointmentRequest $request, Patient $patient)
    {
        if ($this->isAble('store', Appointment::class)) {
            // The patient id from the URL was copied into the body by prepareForValidation()
            return new AppointmentResource(
                Appointment::create($request->mappedAttributes())->refresh()
            );
        }

        return $this->notAuthorized('You are not authorized to create that resource.');
    }

    /**
     * PUT /api/v1/patients/{patient}/appointments/{appointment}
     * Replaces the whole appointment (staff only).
     */
    public function replace(ReplaceAppointmentRequest $request, Patient $patient, Appointment $appointment)
    {
        if ($this->isAble('replace', $appointment)) {
            $appointment->update($request->mappedAttributes());

            return new AppointmentResource($appointment);
        }

        return $this->notAuthorized('You are not authorized to replace that resource.');
    }

    /**
     * PATCH /api/v1/patients/{patient}/appointments/{appointment}
     * Updates only the fields that were sent.
     */
    public function update(UpdateAppointmentRequest $request, Patient $patient, Appointment $appointment)
    {
        if ($this->isAble('update', $appointment)) {
            $appointment->update($request->mappedAttributes(['data.attributes.checkInTime' => 'check_in_time']));

            return new AppointmentResource($appointment);
        }

        return $this->notAuthorized('You are not authorized to update that resource.');
    }

    /**
     * DELETE /api/v1/patients/{patient}/appointments/{appointment}
     * Deletes the appointment. The scoped binding returns 404 if it is not this patient's appointment.
     */
    public function destroy(Patient $patient, Appointment $appointment)
    {
        if ($this->isAble('delete', $appointment)) {
            $appointment->delete();

            return $this->ok('Appointment successfully deleted');
        }

        return $this->notAuthorized('You are not authorized to delete that resource.');
    }
}
