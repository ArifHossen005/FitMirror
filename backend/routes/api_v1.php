<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Mounted at /api/v1 via the `apiPrefix` argument to withRouting() in
| bootstrap/app.php. This file holds tenant-facing routes only.
| Mission Control routes live in routes/api_mission.php (Phase 1.C),
| mounted separately at /api/v1/mission with their own guard.
|
*/

Route::get('/health', HealthController::class)
    ->name('api.v1.health');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
