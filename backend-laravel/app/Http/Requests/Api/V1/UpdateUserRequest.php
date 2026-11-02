<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * Validates the body of PATCH /users/{user}: only the fields sent are checked.
 */
class UpdateUserRequest extends BaseUserRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.attributes.name' => 'sometimes|string',
            'data.attributes.email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'data.attributes.role' => 'sometimes|string|in:superadmin,clinic_admin,receptionist,dentist,patient',
            'data.attributes.status' => 'sometimes|string',
            'data.attributes.password' => 'sometimes|string|min:8',
            'data.relationships.clinic.data.id' => 'sometimes|integer|exists:clinics,clinic_id',
            'data.relationships.branch.data.id' => 'sometimes|integer|exists:branches,branch_id',
        ];
    }
}
