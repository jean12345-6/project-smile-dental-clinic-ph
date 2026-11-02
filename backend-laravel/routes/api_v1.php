<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientAppointmentsController;
use App\Http\Controllers\Api\V1\PatientsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\SlotController;

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
    Route::get('slots', [SlotController::class, 'index']);

    // GET /appointments (list), GET /appointments/{appointment} (one),
    // POST /appointments (book), DELETE /appointments/{appointment}
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'show', 'store', 'destroy']);

    // PUT replaces the whole appointment (our own method name: replace)
    Route::put('appointments/{appointment}', [AppointmentController::class, 'replace']);

    // PATCH updates only the fields that were sent
    Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);

    // Patients are read only: list and show
    Route::apiResource('patients', PatientsController::class)->only(['index', 'show']);

    // Appointments of one patient: GET /patients/{patient}/appointments
        Route::apiResource('patients.appointments', PatientAppointmentsController::class)
        ->only(['index', 'store', 'destroy'])
        ->scoped(); // the appointment must belong to the patient in the URL (otherwise 404)

    // PUT and PATCH use our own method names, so they are registered by hand
    Route::put('patients/{patient}/appointments/{appointment}', [PatientAppointmentsController::class, 'replace'])
        ->scopeBindings();
    Route::patch('patients/{patient}/appointments/{appointment}', [PatientAppointmentsController::class, 'update'])
        ->scopeBindings();
            // Users (management): list, create, show and delete use the resource routes
    Route::apiResource('users', UserController::class)->except(['update']);
    // PUT and PATCH use our own method names, so they are registered by hand
    Route::put('users/{user}', [UserController::class, 'replace']);
    Route::patch('users/{user}', [UserController::class, 'update']);
});
