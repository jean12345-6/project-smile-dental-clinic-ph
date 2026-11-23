<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\AppointmentFilter;
use App\Http\Requests\Api\V1\ReplaceAppointmentRequest;
use App\Http\Requests\Api\V1\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\UpdateAppointmentRequest;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;
use App\Support\Scope;

/**
 * @group Appointments
 *
 * Book, read, reschedule and cancel appointments.
 */
class AppointmentController extends APIController
{
    /**
     * GET /api/v1/appointments
     */
    public function index(AppointmentFilter $filters)
    {
        return AppointmentResource::collection(
            self::ownRows(Appointment::filter($filters), request()->user())->paginate()->withQueryString()
        );
    }

    /**
     * Least privilege on the appointment list (WST criterion 3).
     * The clinic scope already keeps each clinic apart; on top of that a patient sees only
     * their own appointments and a dentist only their own patients' appointments.
     * Staff who run the front desk (receptionist, admins) still see the whole clinic.
     */
    public static function ownRows($q, $user)
    {
        if ($user?->role === 'patient') {
            $q->whereIn('patient_id', fn ($s) => $s->select('patient_id')->from('patients')->where('user_id', $user->id));
        }

        return Scope::ownDentist($q, 'dentist_id', $user);
    }

    /** May this user read this one appointment? Same rule as the list. */
    public static function canSee(Appointment $appointment, $user): bool
    {
        if ($user?->role === 'patient') {
            return (int) $appointment->patient?->user_id === (int) $user->id;
        }
        if ($did = Scope::dentistId($user)) {
            return (int) $appointment->dentist_id === $did;
        }

        return true;
    }

    /**
     * POST /api/v1/appointments
     * Creates an appointment, but only if the policy allows this user to do so.
     */
    public function store(StoreAppointmentRequest $request)
    {
        // Ask AppointmentPolicy::store() (checks the ability stored in the token)
        if ($this->isAble('store', Appointment::class)) {
            $appointment = Appointment::create($request->mappedAttributes());

            return new AppointmentResource($appointment->refresh());
        }

        return $this->notAuthorized('You are not authorized to create that resource.');
    }

    /**
     * GET /api/v1/appointments/{appointment}
     */
    public function show(Appointment $appointment)
    {
        if (! self::canSee($appointment, request()->user())) {
            return $this->notAuthorized('You are not allowed to view this appointment.');
        }
        if ($this->include('patient')) {
            return new AppointmentResource($appointment->load('patient'));
        }

        return new AppointmentResource($appointment);
    }

    /**
     * DELETE /api/v1/appointments/{appointment}
     * Deletes the appointment, but only if the policy allows it.
     */
    public function destroy(Appointment $appointment)
    {
        if ($this->isAble('delete', $appointment)) {
            $appointment->delete();

            return $this->ok('Appointment successfully deleted');
        }

        return $this->notAuthorized('You are not authorized to delete that resource.');
    }

    /**
     * PUT /api/v1/appointments/{appointment}
     * Replaces the whole appointment, but only if the policy allows it.
     */
    public function replace(ReplaceAppointmentRequest $request, Appointment $appointment)
    {
        if ($this->isAble('replace', $appointment)) {
            $appointment->update($request->mappedAttributes());

            return new AppointmentResource($appointment);
        }

        return $this->notAuthorized('You are not authorized to replace that resource.');
    }

    /**
     * PATCH /api/v1/appointments/{appointment}
     * Updates only the fields that were sent, but only if the policy allows it.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        if ($this->isAble('update', $appointment)) {
            $appointment->update($request->mappedAttributes(['data.attributes.checkInTime' => 'check_in_time']));

            return new AppointmentResource($appointment);
        }

        return $this->notAuthorized('You are not authorized to update that resource.');
    }
}
