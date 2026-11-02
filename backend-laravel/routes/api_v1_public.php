<?php

use App\Http\Controllers\Api\V1\PublicController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api_v1.php, outside the auth group (anyone can register as a patient)
Route::post('auth/register-patient', [PublicController::class, 'registerPatient'])->middleware('throttle:10,1');
Route::get('auth/clinics', [PublicController::class, 'clinics']);
