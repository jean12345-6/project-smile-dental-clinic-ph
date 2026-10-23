<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

/**
 * Base class for the version 1 API controllers. Shared helpers live here.
 */
class APIController extends Controller
{
    /**
     * Returns true when the client asked for a relationship with ?include=...
     * Example: /appointments?include=patient  ->  include('patient') is true.
     */
    public function include(string $relationship): bool
    {
        // Read the "include" query parameter (null when it is not in the URL)
        $param = request()->get('include');

        if (! isset($param)) {
            return false;
        }

        // ?include=patient,dentist  ->  ['patient', 'dentist']
        $includeValues = explode(',', strtolower($param));

        return in_array(strtolower($relationship), $includeValues);
    }
}