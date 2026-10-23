<?php

namespace App\Http\Requests\Api\V1;

/**
 * Validates the body of PUT /appointments/{appointment}: every field is required.
 */
class ReplaceAppointmentRequest extends BaseAppointmentRequest
{
    /**
     * Who may send this request is decided later by AppointmentPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * PUT replaces the whole resource, so nothing may be missing.
     * (riskFlag and checkInTime are not here: the AI and the check-in desk set those.)
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array',
            'data.attributes' => 'required|array',
            'data.attributes.startTime' => 'required|date',
            'data.attributes.endTime' => 'required|date|after:data.attributes.startTime',
            'data.attributes.status' => 'required|string|in:pending,confirmed,checked_in,completed,cancelled,no_show',
            'data.attributes.bookedVia' => 'required|string|in:chatbot,portal,walk_in',
            'data.attributes.followUpOf' => 'present|nullable|integer|exists:appointments,appointment_id',
            'data.relationships' => 'required|array',
            'data.relationships.patient.data.id' => 'required|integer|exists:patients,patient_id',
            'data.relationships.dentist.data.id' => 'required|integer|exists:dentists,dentist_id',
            'data.relationships.branch.data.id' => 'required|integer|exists:branches,branch_id',
            'data.relationships.service.data.id' => 'required|integer|exists:services,service_id',
        ];
    }

    /**
     * Same messages as the base class, but the status message lists every status.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'data.attributes.status.in' => 'The data.attributes.status value is invalid. Please use pending, confirmed, checked_in, completed, cancelled, or no_show.',
        ]);
    }
}