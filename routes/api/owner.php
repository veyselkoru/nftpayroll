<?php

use App\Http\Controllers\Api\Owner\CompanyController;
use App\Http\Controllers\Api\Owner\DashboardController;
use App\Http\Controllers\Api\Owner\EmployeeController;
use App\Http\Controllers\Api\Owner\PayrollController;
use App\Http\Controllers\Api\Owner\PayrollQueueController;
use App\Http\Controllers\Api\Owner\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:company_owner'])->prefix('owner')->as('owner.')->group(function () {
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('companies.employees', EmployeeController::class);
    Route::apiResource('companies.employees.payrolls', PayrollController::class);

    Route::get('/companies/{company}/employees/{employee}/nfts', [EmployeeController::class, 'nfts']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/{payroll}/queue', [PayrollQueueController::class, 'queue']);
    Route::get('/companies/{company}/employees/{employee}/payrolls/{payroll}/status', [PayrollController::class, 'status']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/{payroll}/mint/retry', [PayrollController::class, 'retryMint']);
    Route::get('/companies/{company}/employees/{employee}/payrolls/{payroll}/decrypt', [PayrollController::class, 'decryptPayload']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/recent-mints', [DashboardController::class, 'recentMints']);

    Route::get('/companies/{company}/nfts', [CompanyController::class, 'nfts']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/bulk', [PayrollController::class, 'bulkStore']);
    Route::post('/companies/{company}/payrolls/bulk', [PayrollController::class, 'bulkStoreForCompany']);

    Route::get('/companies/{company}/users', [UserManagementController::class, 'index']);
    Route::post('/companies/{company}/users', [UserManagementController::class, 'store']);
});
