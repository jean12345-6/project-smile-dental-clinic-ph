<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by every user request (store, replace, update).
 */
class BaseUserRequest extends FormRequest
{
    /**
     * Converts the nested JSON:API payload into columns of the users table.
     * Only keys that were actually sent are returned.
     */
    public function mappedAttributes(array $otherAttributes = []): array
    {
        // JSON path in the request => column in the users table
        $attributeMap = array_merge([
            'data.attributes.name' => 'name',
            'data.attributes.email' => 'email',
            'data.attributes.role' => 'role',
            'data.attributes.status' => 'status',
            'data.attributes.password' => 'password',
            'data.relationships.clinic.data.id' => 'clinic_id',
            'data.relationships.branch.data.id' => 'branch_id',
        ], $otherAttributes);

        $attributesToUpdate = [];

        foreach ($attributeMap as $key => $attribute) {
            if ($this->has($key)) {
                $value = $this->input($key);

                // Never store a plain password: hash it first
                if ($attribute === 'password') {
                    $value = bcrypt($value);
                }

                $attributesToUpdate[$attribute] = $value;
            }
        }

        return $attributesToUpdate;
    }
}
