<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms an Appointment model into the JSON structure our API returns.
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'appointment',
            'id'   => $this->appointment_id,

            'attributes' => [
                'status'    => $this->status,
                'startTime' => $this->start_time,
                'endTime'   => $this->end_time,
                'riskFlag'  => $this->risk_flag,
                'bookedVia' => $this->booked_via,
                // Shown on the detail page only: hidden on the list endpoint
                $this->mergeWhen(! $request->routeIs('appointments.index'), [
                    'checkInTime' => $this->check_in_time,
                    'createdAt'   => $this->created_at,
                    'updatedAt'   => $this->updated_at,
                ]),
            ],

            'relationships' => [
                'patient' => [
                    'data' => ['type' => 'patient', 'id' => $this->patient_id],
                    // Link to the patient's own endpoint
                    'links' => [
                        'self' => route('patients.show', ['patient' => $this->patient_id]),
                    ],
                ],
                'dentist' => [
                    'data' => ['type' => 'dentist', 'id' => $this->dentist_id],
                ],
                'service' => [
                    'data' => ['type' => 'service', 'id' => $this->service_id],
                ],
                'branch' => [
                    'data' => ['type' => 'branch', 'id' => $this->branch_id],
                ],
            ],

            'includes' => new PatientResource($this->whenLoaded('patient')),

            'links' => [
                'self' => route('appointments.show', ['appointment' => $this->appointment_id]),
            ],
        ];
    }
}