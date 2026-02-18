<?php

use App\Http\Controllers\Api\Admin\ApprovalsController;
use App\Http\Controllers\Api\Admin\BulkOperationsController;
use App\Http\Controllers\Api\Admin\ComplianceController;
use App\Http\Controllers\Api\Admin\CostReportsController;
use App\Http\Controllers\Api\Admin\ExportsController;
use App\Http\Controllers\Api\Admin\IntegrationsController;
use App\Http\Controllers\Api\Admin\NotificationsController;
use App\Http\Controllers\Api\Admin\OperationsController;
use App\Http\Controllers\Api\Admin\RolesController;
use App\Http\Controllers\Api\Admin\SystemHealthController;
use App\Http\Controllers\Api\Admin\TemplatesController;
use App\Http\Controllers\Api\Admin\WalletsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('operations')->middleware('can:operations.manage')->group(function () {
        Route::get('/jobs', [OperationsController::class, 'jobs']);
        Route::post('/jobs/{id}/retry', [OperationsController::class, 'retry']);
        Route::post('/jobs/{id}/cancel', [OperationsController::class, 'cancel']);
        Route::get('/metrics', [OperationsController::class, 'metrics']);
    });

    Route::prefix('approvals')->middleware('can:approvals.manage')->group(function () {
        Route::get('/', [ApprovalsController::class, 'index']);
        Route::post('/{id}/approve', [ApprovalsController::class, 'approve']);
        Route::post('/{id}/reject', [ApprovalsController::class, 'reject']);
        Route::get('/metrics', [ApprovalsController::class, 'metrics']);
    });

    Route::prefix('compliance')->middleware('can:compliance.view')->group(function () {
        Route::get('/audit-logs', [ComplianceController::class, 'auditLogs']);
        Route::get('/security-events', [ComplianceController::class, 'securityEvents']);
        Route::get('/export-history', [ComplianceController::class, 'exportHistory']);
    });

    Route::prefix('notifications')->middleware('can:notifications.manage')->group(function () {
        Route::get('/', [NotificationsController::class, 'index']);
        Route::post('/{id}/read', [NotificationsController::class, 'read']);
        Route::post('/read-all', [NotificationsController::class, 'readAll']);
        Route::get('/metrics', [NotificationsController::class, 'metrics']);
    });

    Route::prefix('integrations')->middleware('can:integrations.manage')->group(function () {
        Route::get('/', [IntegrationsController::class, 'index']);
        Route::post('/', [IntegrationsController::class, 'store']);
        Route::put('/{id}', [IntegrationsController::class, 'update']);
        Route::post('/{id}/test', [IntegrationsController::class, 'test']);
        Route::get('/webhooks/logs', [IntegrationsController::class, 'webhookLogs']);
    });

    Route::prefix('templates')->middleware('can:templates.manage')->group(function () {
        Route::get('/', [TemplatesController::class, 'index']);
        Route::post('/', [TemplatesController::class, 'store']);
        Route::put('/{id}', [TemplatesController::class, 'update']);
        Route::post('/{id}/publish', [TemplatesController::class, 'publish']);
        Route::get('/metrics', [TemplatesController::class, 'metrics']);
    });

    Route::prefix('wallets')->middleware('can:wallets.manage')->group(function () {
        Route::get('/', [WalletsController::class, 'index']);
        Route::post('/validate', [WalletsController::class, 'validateWallet']);
        Route::post('/bulk-validate', [WalletsController::class, 'bulkValidate']);
        Route::get('/metrics', [WalletsController::class, 'metrics']);
    });

    Route::prefix('bulk-operations')->middleware('can:bulk.manage')->group(function () {
        Route::get('/', [BulkOperationsController::class, 'index']);
        Route::post('/import', [BulkOperationsController::class, 'import']);
        Route::post('/{id}/retry', [BulkOperationsController::class, 'retry']);
        Route::get('/metrics', [BulkOperationsController::class, 'metrics']);
    });

    Route::prefix('cost-reports')->middleware('can:cost-reports.view')->group(function () {
        Route::get('/summary', [CostReportsController::class, 'summary']);
        Route::get('/by-company', [CostReportsController::class, 'byCompany']);
        Route::get('/by-network', [CostReportsController::class, 'byNetwork']);
    });

    Route::prefix('roles')->middleware('can:roles.manage')->group(function () {
        Route::get('/', [RolesController::class, 'index']);
        Route::post('/', [RolesController::class, 'store']);
        Route::put('/{id}', [RolesController::class, 'update']);
        Route::post('/{id}/assign-users', [RolesController::class, 'assignUsers']);
        Route::get('/metrics', [RolesController::class, 'metrics']);
    });

    Route::prefix('exports')->middleware('can:exports.manage')->group(function () {
        Route::get('/', [ExportsController::class, 'index']);
        Route::post('/', [ExportsController::class, 'store']);
        Route::get('/{id}/download', [ExportsController::class, 'download']);
        Route::get('/metrics', [ExportsController::class, 'metrics']);
    });

    Route::prefix('system-health')->middleware('can:system-health.view')->group(function () {
        Route::get('/overview', [SystemHealthController::class, 'overview']);
        Route::get('/services', [SystemHealthController::class, 'services']);
        Route::get('/incidents', [SystemHealthController::class, 'incidents']);
    });
});
