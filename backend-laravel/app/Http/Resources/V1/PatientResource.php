<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Patient model into the JSON structure our API returns.
 */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'patient',
            'id'   => $this->patient_id,
            'attributes' => [
                'fullName' => $this->full_name,
                'email'    => $this->email,
                // These fields appear only on the patients endpoints
                $this->mergeWhen($request->routeIs('patients.*'), [
                    'contactNo'     => $this->contact_no,
                    'birthdate'     => $this->birthdate,
                    'noShowCount'   => $this->no_show_count,
                    'createdAt'     => $this->created_at,
                    'updatedAt'     => $this->updated_at,
                ]),
                // Sensitive and long: shown on the detail page only
                'medicalHistory' => $this->when(
                    $request->routeIs('patients.show'),
                    $this->medical_history
                ),
            ],
            // Link to this patient
            'links' => [
                'self' => route('patients.show', ['patient' => $this->patient_id]),
            ],
        ];
    }
}