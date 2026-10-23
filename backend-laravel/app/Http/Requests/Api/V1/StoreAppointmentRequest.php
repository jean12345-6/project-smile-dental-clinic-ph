<?php

namespace App\Http\Requests\Api\V1;

/**
 * Validates the body of POST /appointments.
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
     * Validation rules for booking an appointment.
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array',
            'data.attributes' => 'required|array',
            'data.attributes.startTime' => 'required|date|after:now',
            'data.attributes.endTime' => 'required|date|after:data.attributes.startTime',
            'data.attributes.status' => 'sometimes|string|in:pending,confirmed',
            'data.attributes.bookedVia' => 'sometimes|string|in:chatbot,portal,walk_in',
            'data.attributes.followUpOf' => 'sometimes|nullable|integer|exists:appointments,appointment_id',
            'data.relationships.patient.data.id' => 'required|integer|exists:patients,patient_id',
            'data.relationships.dentist.data.id' => 'required|integer|exists:dentists,dentist_id',
            'data.relationships.branch.data.id' => 'required|integer|exists:branches,branch_id',
            'data.relationships.service.data.id' => 'required|integer|exists:services,service_id',
        ];
    }
}