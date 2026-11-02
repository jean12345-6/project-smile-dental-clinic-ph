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
class UserController extends APIController
{
    /**
     * GET /api/v1/users
     * Lists every user with the filters and sorting from the URL.
     */
    public function index(UserFilter $filters)
    {
        return UserResource::collection(
            User::filter($filters)->paginate()->withQueryString()
        );
    }

    /**
     * POST /api/v1/users
     * Creates a user (needs the user:create ability).
     */
    public function store(StoreUserRequest $request)
    {
        if ($this->isAble('store', User::class)) {
            // The new user joins the clinic of the admin creating them, unless a clinic was sent
            $attributes = array_merge(
                ['clinic_id' => $request->user()->clinic_id],
                $request->mappedAttributes()
            );

            return new UserResource(User::create($attributes));
        }

        return $this->notAuthorized('You are not authorized to create that resource.');
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user)
    {
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
