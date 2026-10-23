<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by every appointment request (store now, replace and update later).
 */
class BaseAppointmentRequest extends FormRequest
{
    /**
     * Converts the nested JSON:API payload into database columns.
     * Only keys that were actually sent are returned, which is what makes PATCH work.
     *
     * Example: data.attributes.startTime  ->  start_time
     */
    public function mappedAttributes(array $otherAttributes = []): array
    {
        // JSON path in the request  =>  column in the appointments table
        $attributeMap = array_merge([
            'data.attributes.startTime' => 'start_time',
            'data.attributes.endTime' => 'end_time',
            'data.attributes.status' => 'status',
            'data.attributes.bookedVia' => 'booked_via',
            'data.attributes.followUpOf' => 'follow_up_of',
            'data.relationships.patient.data.id' => 'patient_id',
            'data.relationships.dentist.data.id' => 'dentist_id',
            'data.relationships.branch.data.id' => 'branch_id',
            'data.relationships.service.data.id' => 'service_id',
        ], $otherAttributes);

        $attributesToUpdate = [];

        foreach ($attributeMap as $key => $attribute) {
            // Keep the value only if the client sent that field
            if ($this->has($key)) {
                $attributesToUpdate[$attribute] = $this->input($key);
            }
        }

        return $attributesToUpdate;
    }

    /**
     * Custom validation messages. The key format is "field.rule".
     */
    public function messages(): array
    {
        return [
            'data.attributes.status.in' => 'The data.attributes.status value is invalid. Please use pending or confirmed.',
            'data.attributes.bookedVia.in' => 'The data.attributes.bookedVia value is invalid. Please use chatbot, portal, or walk_in.',
        ];
    }
}