<?php

namespace App\Http\Requests\Api\V1;

use App\Permissions\V1\Abilities;

/**
 * Validates the body of PATCH /appointments/{appointment}: only the fields sent are checked.
 */
class UpdateAppointmentRequest extends BaseAppointmentRequest
{
    /**
     * Who may send this request is decided later by AppointmentPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "sometimes" means: validate the field only when it was sent.
     */
    public function rules(): array
    {
        // endTime must be after startTime, but only when startTime is in the same request
        $endTimeRule = 'sometimes|date';

        if ($this->has('data.attributes.startTime')) {
            $endTimeRule .= '|after:data.attributes.startTime';
        }

        $rules = [
            'data.attributes.startTime' => 'sometimes|date|after:now',
            'data.attributes.endTime' => $endTimeRule,
            'data.attributes.status' => 'sometimes|string|in:pending,confirmed,checked_in,completed,cancelled,no_show',
            'data.attributes.bookedVia' => 'sometimes|string|in:chatbot,portal,walk_in',
            'data.attributes.checkInTime' => 'sometimes|nullable|date',
            'data.attributes.followUpOf' => 'sometimes|nullable|integer|exists:appointments,appointment_id',
            'data.relationships.patient.data.id' => 'sometimes|integer|exists:patients,patient_id',
            'data.relationships.dentist.data.id' => 'sometimes|integer|exists:dentists,dentist_id',
            'data.relationships.branch.data.id' => 'sometimes|integer|exists:branches,branch_id',
            'data.relationships.service.data.id' => 'sometimes|integer|exists:services,service_id',
        ];

        // Users who may only update their own appointments cannot change the patient (the owner)
        if ($this->user()->tokenCan(Abilities::UpdateOwnAppointment)) {
            $rules['data.relationships.patient.data.id'] = 'prohibited';
        }

        return $rules;
    }

    /**
     * Same messages as the base class, plus the status and patient messages.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'data.attributes.status.in' => 'The data.attributes.status value is invalid. Please use pending, confirmed, checked_in, completed, cancelled, or no_show.',
            'data.relationships.patient.data.id.prohibited' => 'You may not change the patient of an appointment.',
        ]);
    }
}
