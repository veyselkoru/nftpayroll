<?php

use App\Http\Controllers\Api\Manager\CompanyController;
use App\Http\Controllers\Api\Manager\DashboardController;
use App\Http\Controllers\Api\Manager\EmployeeController;
use App\Http\Controllers\Api\Manager\PayrollController;
use App\Http\Controllers\Api\Manager\PayrollQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:company_manager'])->prefix('manager')->as('manager.')->group(function () {
    Route::apiResource('companies.employees', EmployeeController::class);
    Route::apiResource('companies.employees.payrolls', PayrollController::class);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    Route::get('/companies/{company}/nfts', [CompanyController::class, 'nfts']);

    Route::get('/companies/{company}/employees/{employee}/nfts', [EmployeeController::class, 'nfts']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/{payroll}/queue', [PayrollQueueController::class, 'queue']);
    Route::get('/companies/{company}/employees/{employee}/payrolls/{payroll}/status', [PayrollController::class, 'status']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/{payroll}/mint/retry', [PayrollController::class, 'retryMint']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/recent-mints', [DashboardController::class, 'recentMints']);

    Route::post('/companies/{company}/employees/{employee}/payrolls/bulk', [PayrollController::class, 'bulkStore']);
    Route::post('/companies/{company}/payrolls/bulk', [PayrollController::class, 'bulkStoreForCompany']);
});
