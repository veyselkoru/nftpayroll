<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController as LegacyCompanyController;
use App\Http\Controllers\Api\DashboardController as LegacyDashboardController;
use App\Http\Controllers\Api\EmployeeController as LegacyEmployeeController;
use App\Http\Controllers\Api\PayrollController as LegacyPayrollController;
use App\Http\Controllers\Api\PayrollQueueController as LegacyPayrollQueueController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'abi_exists' => Storage::disk('local')->exists('x.txt'),
]));

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| Legacy Frontend Compatibility Routes
|--------------------------------------------------------------------------
| Eski frontend /api/companies... endpointlerini kullanmaya devam etsin.
| Yetki kontrolü role middleware + controller içi şirket erişim kontrolleriyle korunur.
*/
Route::middleware(['auth:sanctum', 'role:company_owner,company_manager'])->group(function () {
    Route::apiResource('companies', LegacyCompanyController::class);
    Route::apiResource('companies.employees', LegacyEmployeeController::class);
    Route::apiResource('companies.employees.payrolls', LegacyPayrollController::class);

    Route::get('/companies/{company}/employees/{employee}/nfts', [LegacyEmployeeController::class, 'nfts']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/{payroll}/queue', [LegacyPayrollQueueController::class, 'queue']);
    Route::get('/companies/{company}/employees/{employee}/payrolls/{payroll}/status', [LegacyPayrollController::class, 'status']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/{payroll}/mint/retry', [LegacyPayrollController::class, 'retryMint']);
    Route::get('/companies/{company}/employees/{employee}/payrolls/{payroll}/decrypt', [LegacyPayrollController::class, 'decryptPayload']);

    Route::get('/dashboard/summary', [LegacyDashboardController::class, 'summary']);
    Route::get('/dashboard/recent-mints', [LegacyDashboardController::class, 'recentMints']);
    Route::get('/companies/{company}/nfts', [LegacyCompanyController::class, 'nfts']);
    Route::post('/companies/{company}/employees/{employee}/payrolls/bulk', [LegacyPayrollController::class, 'bulkStore']);
    Route::post('/companies/{company}/payrolls/bulk', [LegacyPayrollController::class, 'bulkStoreForCompany']);
});

require __DIR__.'/api/owner.php';
require __DIR__.'/api/manager.php';
require __DIR__.'/api/employee.php';
require __DIR__.'/api/admin-modules.php';
