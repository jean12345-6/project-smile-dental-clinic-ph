<?php

namespace App\Http\Requests\Api\V1;

/**
 * Validates the body of POST /users.
 */
class StoreUserRequest extends BaseUserRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => 'required|array',
            'data.attributes' => 'required|array',
            'data.attributes.name' => 'required|string',
            // unique:users,email rejects an email that is already taken
            'data.attributes.email' => 'required|email|unique:users,email',
            'data.attributes.role' => 'required|string|in:superadmin,clinic_admin,receptionist,dentist,patient',
            'data.attributes.status' => 'sometimes|string',
            'data.attributes.password' => 'required|string|min:8',
            'data.relationships.clinic.data.id' => 'sometimes|integer|exists:clinics,clinic_id',
            'data.relationships.branch.data.id' => 'sometimes|integer|exists:branches,branch_id',
        ];
    }
}
