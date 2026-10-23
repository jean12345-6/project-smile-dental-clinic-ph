<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms an Appointment model into the JSON structure our API returns.
 */
class AppointmentResource extends JsonResource
{
    /**
     * Build the array that becomes the JSON response.
     */
    public function toArray(Request $request): array
    {
        return [
            // What kind of object this is
            'type' => 'appointment',
            'id'   => $this->appointment_id,

            // The appointment's own fields, renamed to camelCase for the client
            'attributes' => [
                'status'      => $this->status,
                'startTime'   => $this->start_time,
                'endTime'     => $this->end_time,
                'riskFlag'    => $this->risk_flag,
                'bookedVia'   => $this->booked_via,
                'checkInTime' => $this->check_in_time,
                'createdAt'   => $this->created_at,
                'updatedAt'   => $this->updated_at,
            ],

            // Related objects: for now only the ids
            'relationships' => [
                'patient' => [
                    'data' => ['type' => 'patient', 'id' => $this->patient_id],
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

            // Link to this appointment
            'links' => [
                'self' => route('appointments.show', ['appointment' => $this->appointment_id]),
            ],
        ];
    }
}