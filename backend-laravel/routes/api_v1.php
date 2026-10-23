<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use Illuminate\Support\Facades\Route;

// apiResource creates the standard REST routes; only() keeps just the one we need now.
// GET /api/v1/appointments -> AppointmentController@index
Route::apiResource('appointments', AppointmentController::class)->only(['index']);