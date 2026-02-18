<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\SystemHealthSnapshotResource;
use App\Models\Admin\SystemHealthSnapshot;
use Illuminate\Http\Request;

class SystemHealthController extends BaseAdminController
{
    public function overview()
    {
        $base = SystemHealthSnapshot::query();

        return $this->ok([
            'total_snapshots' => (clone $base)->count(),
            'healthy_services' => (clone $base)->where('status', 'healthy')->count(),
            'warning_services' => (clone $base)->where('status', 'warning')->count(),
            'down_services' => (clone $base)->where('status', 'down')->count(),
        ], 'System health overview');
    }

    public function services(Request $request)
    {
        $query = $this->listQuery->apply($request, SystemHealthSnapshot::query(), ['service', 'status'], ['id', 'service', 'status', 'captured_at', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, SystemHealthSnapshotResource::class, 'System services listed');
    }

    public function incidents(Request $request)
    {
        $query = $this->listQuery->apply($request, SystemHealthSnapshot::query()->whereIn('status', ['warning', 'down']), ['service', 'status'], ['id', 'service', 'status', 'captured_at', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, SystemHealthSnapshotResource::class, 'System incidents listed');
    }
}
