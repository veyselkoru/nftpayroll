<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\OperationJobResource;
use App\Models\Admin\OperationJob;
use Illuminate\Http\Request;

class OperationsController extends BaseAdminController
{
    public function jobs(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, OperationJob::query()), ['name', 'type', 'error_message'], ['id', 'name', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, OperationJobResource::class, 'Operations jobs listed');
    }

    public function retry(Request $request, int $id)
    {
        $job = $this->findInCompanyOrFail($request, OperationJob::class, $id);
        $job->update(['status' => 'queued', 'error_message' => null]);
        $this->auditLog->log($request->user(), 'operations', 'retry', $job);

        return $this->ok(new OperationJobResource($job), 'Job queued for retry');
    }

    public function cancel(Request $request, int $id)
    {
        $job = $this->findInCompanyOrFail($request, OperationJob::class, $id);
        $job->update(['status' => 'cancelled']);
        $this->auditLog->log($request->user(), 'operations', 'cancel', $job);

        return $this->ok(new OperationJobResource($job), 'Job cancelled');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, OperationJob::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'queued' => (clone $base)->where('status', 'queued')->count(),
            'running' => (clone $base)->where('status', 'running')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ], 'Operations metrics');
    }
}
