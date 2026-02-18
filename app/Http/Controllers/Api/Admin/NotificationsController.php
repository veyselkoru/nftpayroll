<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\NotificationEventResource;
use App\Models\Admin\NotificationEvent;
use Illuminate\Http\Request;

class NotificationsController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, NotificationEvent::query()), ['title', 'body', 'channel'], ['id', 'status', 'is_read', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, NotificationEventResource::class, 'Notifications listed');
    }

    public function read(Request $request, int $id)
    {
        $notification = $this->findInCompanyOrFail($request, NotificationEvent::class, $id);
        $notification->update(['is_read' => true, 'read_at' => now()]);
        $this->auditLog->log($request->user(), 'notifications', 'read', $notification);

        return $this->ok(new NotificationEventResource($notification), 'Notification marked as read');
    }

    public function readAll(Request $request)
    {
        $this->scopeCompany($request, NotificationEvent::query())->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
        $this->auditLog->log($request->user(), 'notifications', 'read_all');

        return $this->ok([], 'All notifications marked as read');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, NotificationEvent::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->where('is_read', false)->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
        ], 'Notification metrics');
    }
}
