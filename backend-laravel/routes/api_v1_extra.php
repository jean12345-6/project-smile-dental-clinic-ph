<?php

use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Support\Boot;
use Illuminate\Support\Facades\Route;

Boot::register(); // clinic isolation, booking lock and audit hooks

// Loaded inside the auth:api group of routes/api_v1.php

Route::get('stats', [StatsController::class, 'index']);

Route::post('chat', [ChatController::class, 'send']);
Route::get('chat/history', [ChatController::class, 'history']);
Route::get('chat/replies', [\App\Http\Controllers\Api\V1\StaffInboxController::class, 'replies']);
Route::get('chat/inbox', [\App\Http\Controllers\Api\V1\StaffInboxController::class, 'index']);
Route::get('chat/inbox/{patientId}', [\App\Http\Controllers\Api\V1\StaffInboxController::class, 'thread']);
Route::post('chat/inbox/{patientId}/reply', [\App\Http\Controllers\Api\V1\StaffInboxController::class, 'reply']);

Route::get('queue', [WorkflowController::class, 'queue']);
Route::post('queue/walkin', [WorkflowController::class, 'walkIn']);
Route::post('queue/{id}/status', [WorkflowController::class, 'setStatus']);
Route::post('queue/{id}/followup', [WorkflowController::class, 'followUp']);
Route::post('invoices/generate', [WorkflowController::class, 'generateInvoice']);
Route::get('patient-summary/{id}', [WorkflowController::class, 'summary']);
Route::get('reports', [WorkflowController::class, 'reports']);
Route::post('reminders/run', [WorkflowController::class, 'runReminders']);
Route::post('waitlist/{id}/confirm', [WorkflowController::class, 'confirmOffer']);
Route::get('app-settings', [WorkflowController::class, 'settings']);
Route::put('app-settings', [WorkflowController::class, 'saveSettings']);
Route::post('clinic/logo', [WorkflowController::class, 'clinicLogo']);
Route::post('clinics/{id}/admin', [WorkflowController::class, 'createClinicAdmin']);
Route::post('ocr/upload', [WorkflowController::class, 'ocrUpload']);

// generic clinic modules — keep LAST so the specific routes above win
Route::get('r/{res}', [ClinicController::class, 'index']);
Route::post('r/{res}', [ClinicController::class, 'store']);
Route::get('r/{res}/{id}', [ClinicController::class, 'show']);
Route::match(['put', 'patch'], 'r/{res}/{id}', [ClinicController::class, 'update']);
Route::delete('r/{res}/{id}', [ClinicController::class, 'destroy']);
