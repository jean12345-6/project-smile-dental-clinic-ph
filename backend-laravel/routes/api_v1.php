<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientAppointmentsController;
use App\Http\Controllers\Api\V1\PatientsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // GET /appointments (list), GET /appointments/{appointment} (one),
    // POST /appointments (book), DELETE /appointments/{appointment}
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'show', 'store', 'destroy']);

    // PUT replaces the whole appointment (our own method name: replace)
    Route::put('appointments/{appointment}', [AppointmentController::class, 'replace']);

    // Patients are read only: list and show
    Route::apiResource('patients', PatientsController::class)->only(['index', 'show']);

    // Appointments of one patient: GET /patients/{patient}/appointments
    Route::apiResource('patients.appointments', PatientAppointmentsController::class)->only(['index']);
});