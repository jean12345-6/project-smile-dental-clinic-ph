<?php

namespace App\Http\Requests\Api\V1;

use App\Permissions\V1\Abilities;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates the body of POST /appointments and POST /patients/{patient}/appointments.
 */
class StoreAppointmentRequest extends BaseAppointmentRequest
{
    /**
     * Who may send this request is decided later by AppointmentPolicy,
     * so this class always allows it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * On POST /patients/{patient}/appointments the patient id is in the URL, not in the body.
     * Copy it into the body so the same rules below work for both routes.
     */
    protected function prepareForValidation(): void
    {
        $patient = $this->route('patient');

        if ($patient === null) {
            return;
        }

        $patientId = $patient instanceof Model ? $patient->getKey() : $patient;
        $data = $this->input('data');

        if (! is_array($data)) {
            $data = [];
        }

        $data['relationships']['patient']['data']['id'] = (int) $patientId;

        $this->merge(['data' => $data]);
    }

    /**
     * Validation rules for booking an appointment.
     */
    public function rules(): array
    {
        $patientIdAttr = 'data.relationships.patient.data.id';

        $rules = [
            'data' => 'required|array',
            'data.attributes' => 'required|array',
            'data.attributes.startTime' => 'required|date|after:now',
            'data.attributes.endTime' => 'required|date|after:data.attributes.startTime',
            'data.attributes.status' => 'sometimes|string|in:pending,confirmed',
            'data.attributes.bookedVia' => 'sometimes|string|in:chatbot,portal,walk_in',
            'data.attributes.followUpOf' => 'sometimes|nullable|integer|exists:appointments,appointment_id',
            $patientIdAttr => 'required|integer|exists:patients,patient_id',
            'data.relationships.dentist.data.id' => 'required|integer|exists:dentists,dentist_id',
            'data.relationships.branch.data.id' => 'required|integer|exists:branches,branch_id',
            'data.relationships.service.data.id' => 'required|integer|exists:services,service_id',
        ];

        // A user who may only create their own appointments must book for themselves:
        // size:14 means the patient id must equal 14, the logged-in user's own patient id.
        $user = $this->user();

        if ($user->tokenCan(Abilities::CreateOwnAppointment)) {
            $rules[$patientIdAttr] .= '|size:' . ($user->patient?->patient_id ?? 0);
        }

        return $rules;
    }

    /**
     * Same messages as the base class, plus a clear message for the patient id rule.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'data.relationships.patient.data.id.size' => 'You may only book appointments for yourself.',
        ]);
    }
}
