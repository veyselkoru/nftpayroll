<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Admin\AuditLogService;
use App\Services\Admin\ListQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class BaseAdminController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ListQueryService $listQuery,
        protected AuditLogService $auditLog,
    ) {
    }

    protected function isSuperAdmin(Request $request): bool
    {
        return $request->user()?->normalizedRole() === \App\Models\User::ROLE_COMPANY_OWNER;
    }

    protected function companyId(Request $request): int
    {
        $user = $request->user();
        $companyId = (int) ($user?->company_id ?? 0);

        // Owner eski kayıtlarda company_id boş olabilir; sahip olduğu ilk şirkete otomatik bağla.
        if ($companyId <= 0 && $user && $user->normalizedRole() === \App\Models\User::ROLE_COMPANY_OWNER) {
            $ownedCompanyId = (int) Company::query()->where('owner_id', $user->id)->value('id');
            if ($ownedCompanyId > 0) {
                $user->update(['company_id' => $ownedCompanyId]);
                $companyId = $ownedCompanyId;
            }
        }

        if (! $this->isSuperAdmin($request)) {
            abort_if($companyId <= 0, 403, 'Kullanıcı şirkete atanmamış.');
        }

        return $companyId;
    }

    protected function scopeCompany(Request $request, Builder $query): Builder
    {
        if ($this->isSuperAdmin($request)) {
            return $query;
        }

        return $query->where('company_id', $this->companyId($request));
    }

    protected function findInCompanyOrFail(Request $request, string $modelClass, int $id): Model
    {
        if ($this->isSuperAdmin($request)) {
            return $modelClass::query()->findOrFail($id);
        }

        return $modelClass::query()
            ->where('company_id', $this->companyId($request))
            ->findOrFail($id);
    }
}
