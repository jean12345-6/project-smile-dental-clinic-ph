<?php

namespace App\Http\Requests\Api\V1;

use App\Permissions\V1\Abilities;
use App\Models\Appointment;
use App\Services\V1\SlotService;
use Illuminate\Validation\Validator;

/**
 * Validates the body of PATCH /appointments/{appointment}: only the fields sent are checked.
 */
class UpdateAppointmentRequest extends BaseAppointmentRequest
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
     * Validation rules for updating an appointment (deny by default).
     * Null-safe so Scribe can read the rules while no user is logged in.
     */
    public function rules(): array
    {
        $rules = [
            'data.attributes.startTime' => 'sometimes|date',
            'data.attributes.endTime' => 'sometimes|date',
            'data.attributes.status' => 'sometimes|string|in:pending,confirmed,checked_in,completed,cancelled,no_show',
            'data.attributes.bookedVia' => 'sometimes|string|in:chatbot,portal,walk_in',
            'data.attributes.checkInTime' => 'sometimes|nullable|date',
            'data.attributes.followUpOf' => 'sometimes|nullable|integer|exists:appointments,appointment_id',
            'data.relationships.dentist.data.id' => 'sometimes|integer|exists:dentists,dentist_id',
            'data.relationships.branch.data.id' => 'sometimes|integer|exists:branches,branch_id',
            'data.relationships.service.data.id' => 'sometimes|integer|exists:services,service_id',
        ];

        // The end time must be after the start time, but only when a start time is sent
        if ($this->has('data.attributes.startTime')) {
            $rules['data.attributes.endTime'] .= '|after:data.attributes.startTime';
        }

        // Default: nobody may change the patient of an appointment
        $rules['data.relationships.patient.data.id'] = 'prohibited';

        // Only the broad ability lifts the restriction (staff)
        if ($this->user()?->tokenCan(Abilities::UpdateAppointment)) {
            $rules['data.relationships.patient.data.id'] = 'sometimes|integer|exists:patients,patient_id';
        }

        return $rules;
    }

    /**
     * Same messages as the base class, plus the status and patient messages.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'data.attributes.status.in' => 'The data.attributes.status value is invalid. Please use pending, confirmed, checked_in, completed, cancelled or no_show.',
            'data.relationships.patient.data.id.prohibited' => 'You may not change the patient of an appointment.',
        ]);
    }

    /**
     * Descriptions and examples that Scribe shows in the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'data.attributes.startTime' => [
                'description' => 'New start time.',
                'example' => '2026-12-12 10:00:00',
            ],
            'data.attributes.endTime' => [
                'description' => 'New end time. Must be after the start time.',
                'example' => '2026-12-12 11:00:00',
            ],
            'data.attributes.status' => [
                'description' => 'pending, confirmed, checked_in, completed, cancelled or no_show.',
                'example' => 'confirmed',
            ],
            'data.attributes.bookedVia' => [
                'description' => 'chatbot, portal or walk_in.',
                'example' => 'portal',
            ],
            'data.attributes.checkInTime' => [
                'description' => 'When the patient checked in (optional).',
                'example' => null,
            ],
            'data.attributes.followUpOf' => [
                'description' => 'Id of the earlier appointment this one follows up (optional).',
                'example' => null,
            ],
            'data.relationships.patient.data.id' => [
                'description' => 'Staff only. Patients may not change the patient of an appointment.',
                'example' => null,
            ],
            'data.relationships.dentist.data.id' => [
                'description' => 'The dentist id.',
                'example' => 1,
            ],
            'data.relationships.branch.data.id' => [
                'description' => 'The branch id.',
                'example' => 1,
            ],
            'data.relationships.service.data.id' => [
                'description' => 'The service id.',
                'example' => 1,
            ],
        ];
    }
        /**
     * Runs after the normal rules pass: blocks double-booking when the time or dentist changes.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $appointment = $this->route('appointment');
                if (! $appointment instanceof Appointment) {
                    $appointment = Appointment::find($appointment);
                }
                if (! $appointment) {
                    return;
                }

                $start = $this->input('data.attributes.startTime', $appointment->start_time);
                $end = $this->input('data.attributes.endTime', $appointment->end_time);
                $dentistId = $this->input('data.relationships.dentist.data.id', $appointment->dentist_id);

                $changed = $this->has('data.attributes.startTime')
                    || $this->has('data.attributes.endTime')
                    || $this->has('data.relationships.dentist.data.id');

                if (! $changed) {
                    return;
                }

                if (! app(SlotService::class)->isFree((int) $dentistId, (string) $start, (string) $end, $appointment->getKey())) {
                    $validator->errors()->add(
                        'data.attributes.startTime',
                        'That time is already taken. Please pick another slot.'
                    );
                }
            },
        ];
    }
}
