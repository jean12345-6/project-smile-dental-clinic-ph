<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// The React build is copied into public/ at deploy time. Serve its index.html
// for the root and for any unmatched path, so client-side routes still work on
// a full page load. API paths are excluded so a wrong endpoint still 404s.
Route::get('/', SpaController::class);
Route::fallback(SpaController::class);
