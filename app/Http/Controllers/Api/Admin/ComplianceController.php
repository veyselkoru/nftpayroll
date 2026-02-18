<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\AuditLogResource;
use App\Http\Resources\Admin\ExportJobResource;
use App\Models\Admin\AuditLog;
use App\Models\Admin\ExportJob;
use Illuminate\Http\Request;

class ComplianceController extends BaseAdminController
{
    public function auditLogs(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, AuditLog::query()), ['module', 'action', 'status'], ['id', 'module', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, AuditLogResource::class, 'Audit logs listed');
    }

    public function securityEvents(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, AuditLog::query()->where('module', 'security')), ['action', 'status'], ['id', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, AuditLogResource::class, 'Security events listed');
    }

    public function exportHistory(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, ExportJob::query()), ['name', 'type'], ['id', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, ExportJobResource::class, 'Export history listed');
    }
}
