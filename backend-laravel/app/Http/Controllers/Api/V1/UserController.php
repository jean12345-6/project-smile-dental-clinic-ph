<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Filters\V1\UserFilter;
use App\Http\Requests\Api\V1\ReplaceUserRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;

/**
 * User management for admins: list, create, show, replace, update and delete users.
 */
/**
 * @group Users
 *
 * Manage clinic staff and patient login accounts.
 */
class UserController extends APIController
{
    /**
     * GET /api/v1/users
     * Lists every user with the filters and sorting from the URL.
     */
    public function index(UserFilter $filters)
    {
        // Only administrators manage accounts. Everyone else gets just their own account,
        // so a patient cannot list the clinic's staff and other patients (WST criterion 3).
        $q = User::filter($filters);
        $me = request()->user();
        if (! in_array($me?->role, ['superadmin', 'clinic_admin'], true)) {
            $q->where('id', $me?->id);
        }

        return UserResource::collection($q->paginate()->withQueryString());
    }

    /**
     * POST /api/v1/users
     * Creates a user (needs the user:create ability).
     * A clinic admin can only add users to their own clinic. Only the SuperAdmin can pick a clinic
     * or create another SuperAdmin. Every user except a SuperAdmin must belong to a clinic.
     */
    public function store(StoreUserRequest $request)
    {
        if ($this->isAble('store', User::class)) {
            $me = $request->user();
            $attributes = array_merge(
                ['clinic_id' => $me->clinic_id],
                $request->mappedAttributes()
            );

            // the clinic the SuperAdmin picked (JSON:API clinicId, or clinic_id)
            $picked = $request->input('data.attributes.clinicId') ?: $request->input('data.attributes.clinic_id') ?: $request->input('clinic_id');
            if ($picked && $me->role === 'superadmin') {
                $attributes['clinic_id'] = (int) $picked;
            }

            if ($me->role !== 'superadmin') {
                $attributes['clinic_id'] = $me->clinic_id; // never another clinic
                if (($attributes['role'] ?? '') === 'superadmin') {
                    return $this->notAuthorized('Only the SuperAdmin can create a SuperAdmin.');
                }
            }
            if (empty($attributes['clinic_id']) && ($attributes['role'] ?? '') !== 'superadmin') {
                return $this->error('Choose a clinic for this user.', 422);
            }
            if (empty($attributes['status'])) {
                $attributes['status'] = 'active';
            }

            return new UserResource(User::create($attributes));
        }

        return $this->notAuthorized('You are not authorized to create that resource.');
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user)
    {
        $me = request()->user();
        if (! in_array($me?->role, ['superadmin', 'clinic_admin'], true) && (int) $me?->id !== (int) $user->id) {
            return $this->notAuthorized('You are not allowed to view this account.');
        }

        return new UserResource($user);
    }

    /**
     * PATCH /api/v1/users/{user}
     * Updates only the fields that were sent (needs user:update).
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if ($this->isAble('update', $user)) {
            $user->update($request->mappedAttributes());

            return new UserResource($user);
        }

        return $this->notAuthorized('You are not authorized to update that resource.');
    }

    /**
     * PUT /api/v1/users/{user}
     * Replaces the whole user: every field is required (needs user:replace).
     */
    public function replace(ReplaceUserRequest $request, User $user)
    {
        if ($this->isAble('replace', $user)) {
            $user->update($request->mappedAttributes());

            return new UserResource($user);
        }

        return $this->notAuthorized('You are not authorized to replace that resource.');
    }

    /**
     * DELETE /api/v1/users/{user}
     * Deletes the user (needs user:delete).
     */
    public function destroy(User $user)
    {
        if ($this->isAble('delete', $user)) {
            $user->delete();

            return $this->ok('User successfully deleted');
        }

        return $this->notAuthorized('You are not authorized to delete that resource.');
    }
}
