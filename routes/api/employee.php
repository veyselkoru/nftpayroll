<?php

use App\Http\Controllers\Api\Employee\PortalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:employee'])->prefix('employee')->as('employee.')->group(function () {
    Route::get('/me', [PortalController::class, 'me']);
});
