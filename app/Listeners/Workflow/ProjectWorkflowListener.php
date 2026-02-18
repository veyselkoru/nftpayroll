<?php

namespace App\Listeners\Workflow;

use App\Events\Workflow\ExportRequested;
use App\Events\Workflow\IntegrationWebhookDispatched;
use App\Events\Workflow\IntegrationWebhookFailed;
use App\Events\Workflow\MintFailed;
use App\Events\Workflow\MintQueued;
use App\Events\Workflow\MintRetried;
use App\Events\Workflow\MintStarted;
use App\Events\Workflow\MintSucceeded;
use App\Events\Workflow\PayrollCreated;
use App\Events\Workflow\PayrollDecryptedViewed;
use App\Models\Admin\ApprovalRequest;
use App\Models\Admin\AuditLog;
use App\Models\Admin\IntegrationWebhookLog;
use App\Models\Admin\NotificationEvent;
use App\Models\Admin\OperationJob;
use App\Models\NftMint;
use App\Models\Payroll;

class ProjectWorkflowListener
{
    public function onPayrollCreated(PayrollCreated $event): void
    {
        AuditLog::create([
            'company_id' => $event->companyId,
            'user_id' => $event->triggeredByUserId,
            'module' => 'payroll',
            'action' => 'created',
            'status' => 'success',
            'auditable_type' => 'payroll',
            'auditable_id' => $event->payrollId,
            'meta' => ['employee_id' => $event->employeeId],
        ]);
    }

    public function onMintQueued(MintQueued $event): void
    {
        Payroll::query()->where('id', $event->payrollId)->update([
            'status' => 'queued',
        ]);

        if ($event->nftMintId) {
            NftMint::query()->where('id', $event->nftMintId)->update([
                'status' => 'pending',
                'error_message' => null,
            ]);
        }

        OperationJob::create([
            'company_id' => $event->companyId,
            'employee_id' => $event->employeeId,
            'payroll_id' => $event->payrollId,
            'nft_mint_id' => $event->nftMintId,
            'triggered_by_user_id' => $event->triggeredByUserId,
            'name' => 'Mint payroll NFT',
            'type' => 'mint',
            'status' => 'queued',
            'retry_count' => 0,
            'attempts' => 0,
            'max_attempts' => 5,
            'started_at' => null,
            'finished_at' => null,
        ]);

        AuditLog::create([
            'company_id' => $event->companyId,
            'user_id' => $event->triggeredByUserId,
            'module' => 'mint',
            'action' => 'queued',
            'status' => 'success',
            'auditable_type' => 'payroll',
            'auditable_id' => $event->payrollId,
            'meta' => ['employee_id' => $event->employeeId, 'nft_mint_id' => $event->nftMintId],
        ]);

        $pendingCount = OperationJob::where('company_id', $event->companyId)->where('status', 'queued')->count();
        if ($pendingCount >= (int) env('QUEUE_BACKLOG_NOTIFICATION_THRESHOLD', 50)) {
            NotificationEvent::create([
                'company_id' => $event->companyId,
                'user_id' => $event->triggeredByUserId,
                'title' => 'Queue backlog high',
                'body' => "Queued mint jobs reached {$pendingCount}",
                'channel' => 'in_app',
                'status' => 'queued',
                'is_read' => false,
                'payload' => ['pending_jobs' => $pendingCount],
            ]);
        }
    }

    public function onMintStarted(MintStarted $event): void
    {
        Payroll::query()->where('id', $event->payrollId)->update([
            'status' => 'processing',
        ]);

        NftMint::query()->where('id', $event->nftMintId)->update([
            'status' => 'processing',
        ]);

        $job = OperationJob::where('nft_mint_id', $event->nftMintId)->latest('id')->first();
        if ($job) {
            $job->update([
                'status' => 'running',
                'started_at' => now(),
            ]);
        }
    }

    public function onMintSucceeded(MintSucceeded $event): void
    {
        Payroll::query()->where('id', $event->payrollId)->update([
            'status' => 'sent',
            'ipfs_cid' => NftMint::query()->where('id', $event->nftMintId)->value('ipfs_cid'),
        ]);

        NftMint::query()->where('id', $event->nftMintId)->update([
            'status' => 'sent',
            'tx_hash' => $event->txHash,
            'token_id' => $event->tokenId,
            'error_message' => null,
        ]);

        $job = OperationJob::where('nft_mint_id', $event->nftMintId)->latest('id')->first();
        if ($job) {
            $job->update([
                'status' => 'completed',
                'tx_hash' => $event->txHash,
                'token_id' => $event->tokenId,
                'duration_ms' => $event->durationMs,
                'finished_at' => now(),
            ]);
        }

        AuditLog::create([
            'company_id' => $event->companyId,
            'module' => 'mint',
            'action' => 'succeeded',
            'status' => 'success',
            'auditable_type' => 'payroll',
            'auditable_id' => $event->payrollId,
            'meta' => [
                'employee_id' => $event->employeeId,
                'nft_mint_id' => $event->nftMintId,
                'tx_hash' => $event->txHash,
                'token_id' => $event->tokenId,
            ],
        ]);
    }

    public function onMintFailed(MintFailed $event): void
    {
        Payroll::query()->where('id', $event->payrollId)->update([
            'status' => 'failed',
        ]);

        if ($event->nftMintId) {
            NftMint::query()->where('id', $event->nftMintId)->update([
                'status' => 'failed',
                'error_message' => $event->errorMessage,
            ]);
        }

        $job = OperationJob::where('payroll_id', $event->payrollId)->latest('id')->first();
        if ($job) {
            $job->update([
                'status' => 'failed',
                'error_message' => $event->errorMessage,
                'finished_at' => now(),
            ]);
        }

        AuditLog::create([
            'company_id' => $event->companyId,
            'module' => 'mint',
            'action' => 'failed',
            'status' => 'failed',
            'auditable_type' => 'payroll',
            'auditable_id' => $event->payrollId,
            'meta' => ['employee_id' => $event->employeeId, 'nft_mint_id' => $event->nftMintId, 'error' => $event->errorMessage],
        ]);

        NotificationEvent::create([
            'company_id' => $event->companyId,
            'title' => 'Mint failed',
            'body' => $event->errorMessage,
            'channel' => 'in_app',
            'status' => 'queued',
            'is_read' => false,
            'payload' => ['payroll_id' => $event->payrollId, 'nft_mint_id' => $event->nftMintId],
        ]);
    }

    public function onMintRetried(MintRetried $event): void
    {
        Payroll::query()->where('id', $event->payrollId)->update([
            'status' => 'queued',
        ]);

        NftMint::query()->where('id', $event->nftMintId)->update([
            'status' => 'pending',
            'error_message' => null,
            'tx_hash' => null,
            'token_id' => null,
        ]);

        $job = OperationJob::where('nft_mint_id', $event->nftMintId)->latest('id')->first();
        if ($job) {
            $job->update([
                'status' => 'queued',
                'retry_count' => (int) $job->retry_count + 1,
                'error_message' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);
        }

        AuditLog::create([
            'company_id' => $event->companyId,
            'user_id' => $event->triggeredByUserId,
            'module' => 'mint',
            'action' => 'retried',
            'status' => 'success',
            'auditable_type' => 'payroll',
            'auditable_id' => $event->payrollId,
            'meta' => ['employee_id' => $event->employeeId, 'nft_mint_id' => $event->nftMintId],
        ]);
    }

    public function onPayrollDecryptedViewed(PayrollDecryptedViewed $event): void
    {
        AuditLog::create([
            'company_id' => $event->companyId,
            'user_id' => $event->triggeredByUserId,
            'module' => 'payroll',
            'action' => 'decrypted_viewed',
            'status' => 'success',
            'auditable_type' => 'payroll',
            'auditable_id' => $event->payrollId,
            'meta' => ['employee_id' => $event->employeeId],
        ]);
    }

    public function onExportRequested(ExportRequested $event): void
    {
        AuditLog::create([
            'company_id' => $event->companyId,
            'user_id' => $event->triggeredByUserId,
            'module' => 'exports',
            'action' => 'requested',
            'status' => 'success',
            'auditable_type' => 'export_job',
            'auditable_id' => $event->exportJobId,
            'meta' => [],
        ]);
    }

    public function onIntegrationWebhookDispatched(IntegrationWebhookDispatched $event): void
    {
        IntegrationWebhookLog::create([
            'company_id' => $event->companyId,
            'integration_connection_id' => $event->integrationConnectionId,
            'triggered_by_user_id' => $event->triggeredByUserId,
            'status' => 'success',
            'endpoint' => $event->endpoint,
            'payload' => $event->payload,
            'http_status' => $event->httpStatus,
        ]);
    }

    public function onIntegrationWebhookFailed(IntegrationWebhookFailed $event): void
    {
        IntegrationWebhookLog::create([
            'company_id' => $event->companyId,
            'integration_connection_id' => $event->integrationConnectionId,
            'triggered_by_user_id' => $event->triggeredByUserId,
            'status' => 'failed',
            'endpoint' => $event->endpoint,
            'payload' => $event->payload,
            'http_status' => $event->httpStatus,
            'error_message' => $event->errorMessage,
        ]);

        NotificationEvent::create([
            'company_id' => $event->companyId,
            'user_id' => $event->triggeredByUserId,
            'title' => 'Integration webhook failed',
            'body' => $event->errorMessage,
            'channel' => 'in_app',
            'status' => 'queued',
            'is_read' => false,
            'payload' => ['integration_connection_id' => $event->integrationConnectionId, 'endpoint' => $event->endpoint],
        ]);
    }
}
