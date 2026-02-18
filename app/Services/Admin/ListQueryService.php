<?php

namespace App\Services\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListQueryService
{
    public function apply(Request $request, Builder $query, array $searchable = [], array $sortable = ['created_at']): Builder
    {
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($searchable, $search) {
                foreach ($searchable as $idx => $column) {
                    if ($idx === 0) {
                        $q->where($column, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sortBy = (string) $request->query('sort_by', 'created_at');
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) $request->query('sort_dir', 'desc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        return $query->orderBy($sortBy, $sortDir);
    }

    public function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return max(1, min($perPage, $max));
    }
}
