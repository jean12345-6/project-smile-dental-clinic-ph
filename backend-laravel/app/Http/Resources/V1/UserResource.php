<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a User model into the JSON structure our API returns.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'user',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'email' => $this->email,
                'role' => $this->role,
                'status' => $this->status,
                // These fields appear only on the users endpoints
                $this->mergeWhen($request->routeIs('users.*'), [
                    'clinicId' => $this->clinic_id,
                    'branchId' => $this->branch_id,
                    'createdAt' => $this->created_at,
                    'updatedAt' => $this->updated_at,
                ]),
            ],
            'links' => [
                'self' => route('users.show', ['user' => $this->id]),
            ],
        ];
    }
}
