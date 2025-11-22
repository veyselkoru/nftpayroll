<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollQueueController;
use Illuminate\Support\Facades\Storage;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'abi_exists' => Storage::disk('local')->exists('x.txt'),
]));

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::post(
    '/companies/{company}/employees/{employee}/payrolls/{payroll}/queue',
    [PayrollQueueController::class, 'queue']
)->middleware('auth:sanctum');

Route::get(
    '/companies/{company}/employees/{employee}/payrolls/{payroll}/status',
    [PayrollController::class, 'status']
)->middleware('auth:sanctum');

Route::post(
    '/companies/{company}/employees/{employee}/payrolls/{payroll}/mint/retry',
    [PayrollController::class, 'retryMint']
)->middleware('auth:sanctum');




Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',     [AuthController::class, 'me']);
    Route::post('/logout',[AuthController::class, 'logout']);

    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('companies.employees', EmployeeController::class);
    Route::apiResource('companies.employees.payrolls', PayrollController::class);

    Route::get('/companies/{company}/employees/{employee}/nfts',[EmployeeController::class, 'nfts']);    
});
