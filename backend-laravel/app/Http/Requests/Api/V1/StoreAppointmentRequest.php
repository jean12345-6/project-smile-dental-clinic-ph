<?php

namespace App\Http\Requests\Api\V1;

use App\Permissions\V1\Abilities;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Services\V1\SlotService;
use Illuminate\Validation\Validator;

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
     * Validation rules for booking an appointment (deny by default).
     * Null-safe so Scribe can read the rules while no user is logged in.
     */
    public function rules(): array
    {
        $patientIdAttr = 'data.relationships.patient.data.id';
        $user = $this->user();

        $rules = [
            'data' => 'required|array',
            'data.attributes' => 'required|array',
            'data.attributes.startTime' => 'required|date|after:now',
            'data.attributes.endTime' => 'required|date|after:data.attributes.startTime',
            'data.attributes.status' => 'sometimes|string|in:pending,confirmed',
            'data.attributes.bookedVia' => 'sometimes|string|in:chatbot,portal,walk_in',
            'data.attributes.followUpOf' => 'sometimes|nullable|integer|exists:appointments,appointment_id',
            // Default: the patient must be the logged-in user's own patient record
            $patientIdAttr => 'required|integer|exists:patients,patient_id'
                . ($user ? '|size:' . ($user->patient?->patient_id ?? 0) : ''),
            'data.relationships.dentist.data.id' => 'required|integer|exists:dentists,dentist_id',
            'data.relationships.branch.data.id' => 'required|integer|exists:branches,branch_id',
            'data.relationships.service.data.id' => 'required|integer|exists:services,service_id',
        ];

        // Only the broad ability lifts the restriction (staff)
        if ($user?->tokenCan(Abilities::CreateAppointment)) {
            $rules[$patientIdAttr] = 'required|integer|exists:patients,patient_id';
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

    /**
     * Descriptions and examples that Scribe shows in the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'data.attributes.startTime' => [
                'description' => 'Start of the appointment. Must be in the future.',
                'example' => '2026-12-10 10:00:00',
            ],
            'data.attributes.endTime' => [
                'description' => 'End of the appointment. Must be after the start time.',
                'example' => '2026-12-10 11:00:00',
            ],
            'data.attributes.status' => [
                'description' => 'pending or confirmed.',
                'example' => 'pending',
            ],
            'data.attributes.bookedVia' => [
                'description' => 'How it was booked: chatbot, portal or walk_in.',
                'example' => 'portal',
            ],
            'data.attributes.followUpOf' => [
                'description' => 'Id of the earlier appointment this one follows up (optional).',
                'example' => null,
            ],
            'data.relationships.patient.data.id' => [
                'description' => 'The patient id. Patients may only use their own id. On /patients/{patient}/appointments it is taken from the URL.',
                'example' => 14,
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
     * Runs after the normal rules pass: blocks double-booking.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $dentistId = $this->input('data.relationships.dentist.data.id');
                $start = $this->input('data.attributes.startTime');
                $end = $this->input('data.attributes.endTime');

                if (! $dentistId || ! $start || ! $end) {
                    return;
                }

                if (! app(SlotService::class)->isFree((int) $dentistId, $start, $end)) {
                    $validator->errors()->add(
                        'data.attributes.startTime',
                        'That time is already taken. Please pick another slot.'
                    );

                    return;
                }

                // The clinic must be open. Only the dentist's own schedule was checked before,
                // so a booking could land on a closed day or after closing time.
                $clinicId = ClinicHours::clinicFor((int) $this->input('data.relationships.branch.data.id'), $this->user());
                $date = Carbon::parse($start)->toDateString();
                $hours = ClinicHours::on($clinicId, $date);

                if ($hours === null || Carbon::parse($start)->lt($hours['open']) || Carbon::parse($end)->gt($hours['close'])) {
                    $validator->errors()->add('data.attributes.startTime', ClinicHours::closedMessage($clinicId, $date));
                }
            },
        ];
    }
}
