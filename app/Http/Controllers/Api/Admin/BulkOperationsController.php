<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\BulkImportRequest;
use App\Http\Resources\Admin\BulkOperationRunResource;
use App\Models\Admin\BulkOperationRun;
use Illuminate\Http\Request;

class BulkOperationsController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, BulkOperationRun::query()), ['name', 'type'], ['id', 'name', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, BulkOperationRunResource::class, 'Bulk operations listed');
    }

    public function import(BulkImportRequest $request)
    {
        $row = BulkOperationRun::create($request->validated() + [
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'status' => 'queued',
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => now(),
        ]);

        $this->auditLog->log($request->user(), 'bulk_operations', 'import', $row, $request->validated());

        return $this->ok(new BulkOperationRunResource($row), 'Bulk import queued');
    }

    public function retry(Request $request, int $id)
    {
        $row = $this->findInCompanyOrFail($request, BulkOperationRun::class, $id);
        $row->update(['status' => 'queued']);
        $this->auditLog->log($request->user(), 'bulk_operations', 'retry', $row);

        return $this->ok(new BulkOperationRunResource($row), 'Bulk operation queued for retry');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, BulkOperationRun::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'queued' => (clone $base)->where('status', 'queued')->count(),
            'running' => (clone $base)->where('status', 'running')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ], 'Bulk operations metrics');
    }
}
