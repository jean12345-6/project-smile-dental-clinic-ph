<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base class for the version 1 API controllers. Shared helpers live here.
 */
class APIController extends Controller
{
    // Gives every controller that extends this class the ok(), success() and error() helpers
    use ApiResponses, AuthorizesRequests;

    /**
     * Returns true when the client asked for a relationship with ?include=...
     * Example: /appointments?include=patient -> include('patient') is true.
     */
    public function include(string $relationship): bool
    {
        $param = request()->get('include');

        if (! isset($param)) {
            return false;
        }

        $includeValues = explode(',', strtolower($param));

        return in_array(strtolower($relationship), $includeValues);
    }

    /**
     * Asks the policy whether the logged-in user may do $ability on $target.
     * Returns true or false instead of throwing an exception.
     */
    public function isAble(string $ability, mixed $target): bool
    {
        try {
            // authorize() throws AuthorizationException when the policy says no
            $this->authorize($ability, $target);

            return true;
        } catch (AuthorizationException $e) {
            return false;
        }
    }
}