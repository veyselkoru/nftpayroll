<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\ExportStoreRequest;
use App\Http\Resources\Admin\ExportJobResource;
use App\Events\Workflow\ExportRequested;
use App\Models\Admin\ExportJob;
use Illuminate\Http\Request;

class ExportsController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, ExportJob::query()), ['name', 'type'], ['id', 'name', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, ExportJobResource::class, 'Exports listed');
    }

    public function store(ExportStoreRequest $request)
    {
        $job = ExportJob::create($request->validated() + [
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'status' => 'ready',
            'file_path' => 'exports/export-'.$request->user()->id.'-'.now()->timestamp.'.csv',
            'completed_at' => now(),
        ]);

        event(new ExportRequested(
            companyId: (int) $job->company_id,
            exportJobId: (int) $job->id,
            triggeredByUserId: $request->user()->id,
        ));

        $this->auditLog->log($request->user(), 'exports', 'create', $job, $request->validated());

        return $this->ok(new ExportJobResource($job), 'Export created');
    }

    public function download(Request $request, int $id)
    {
        $job = $this->findInCompanyOrFail($request, ExportJob::class, $id);
        $job->update(['downloaded_at' => now()]);
        $this->auditLog->log($request->user(), 'exports', 'download', $job);

        return $this->ok([
            'id' => $job->id,
            'download_url' => url('/storage/'.$job->file_path),
        ], 'Export download link ready');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, ExportJob::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->where('status', 'ready')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'downloaded' => (clone $base)->whereNotNull('downloaded_at')->count(),
        ], 'Export metrics');
    }
}
