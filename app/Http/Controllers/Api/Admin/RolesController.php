<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\RoleAssignUsersRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Http\Resources\Admin\RoleDefinitionResource;
use App\Models\Admin\RoleDefinition;
use App\Models\User;
use Illuminate\Http\Request;

class RolesController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, RoleDefinition::query()), ['name', 'key'], ['id', 'name', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, RoleDefinitionResource::class, 'Roles listed');
    }

    public function store(RoleStoreRequest $request)
    {
        $role = RoleDefinition::create($request->validated() + ['company_id' => $request->user()->company_id]);
        $this->auditLog->log($request->user(), 'roles', 'create', $role, $request->validated());

        return $this->ok(new RoleDefinitionResource($role), 'Role created');
    }

    public function update(RoleUpdateRequest $request, int $id)
    {
        $role = $this->findInCompanyOrFail($request, RoleDefinition::class, $id);
        $role->update($request->validated());
        $this->auditLog->log($request->user(), 'roles', 'update', $role, $request->validated());

        return $this->ok(new RoleDefinitionResource($role), 'Role updated');
    }

    public function assignUsers(RoleAssignUsersRequest $request, int $id)
    {
        $role = $this->findInCompanyOrFail($request, RoleDefinition::class, $id);
        $usersQuery = User::query()->whereIn('id', $request->validated('user_ids'));
        if (! $this->isSuperAdmin($request)) {
            $usersQuery->where('company_id', $this->companyId($request));
        }
        $users = $usersQuery->get();

        foreach ($users as $user) {
            $user->update(['role' => $role->key]);
        }

        $this->auditLog->log($request->user(), 'roles', 'assign_users', $role, ['user_ids' => $users->pluck('id')->all()]);

        return $this->ok([
            'role' => new RoleDefinitionResource($role),
            'assigned_users_count' => $users->count(),
        ], 'Users assigned to role');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, RoleDefinition::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
        ], 'Role metrics');
    }
}
