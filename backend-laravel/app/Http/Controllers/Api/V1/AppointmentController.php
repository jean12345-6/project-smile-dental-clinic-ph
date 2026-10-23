<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;

class AppointmentController extends Controller
{
    /**
     * GET /api/v1/appointments
     * Returns every appointment. Pagination and filters come in later topics.
     */
    public function index()
    {
        return Appointment::all();
    }
}