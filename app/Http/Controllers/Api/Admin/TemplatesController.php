<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\TemplateStoreRequest;
use App\Http\Requests\Admin\TemplateUpdateRequest;
use App\Http\Resources\Admin\TemplateDefinitionResource;
use App\Models\Admin\TemplateDefinition;
use Illuminate\Http\Request;

class TemplatesController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, TemplateDefinition::query()), ['name', 'type'], ['id', 'name', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, TemplateDefinitionResource::class, 'Templates listed');
    }

    public function store(TemplateStoreRequest $request)
    {
        $template = TemplateDefinition::create($request->validated() + ['user_id' => $request->user()->id, 'company_id' => $request->user()->company_id]);
        $this->auditLog->log($request->user(), 'templates', 'create', $template, $request->validated());

        return $this->ok(new TemplateDefinitionResource($template), 'Template created');
    }

    public function update(TemplateUpdateRequest $request, int $id)
    {
        $template = $this->findInCompanyOrFail($request, TemplateDefinition::class, $id);
        $template->update($request->validated());
        $this->auditLog->log($request->user(), 'templates', 'update', $template, $request->validated());

        return $this->ok(new TemplateDefinitionResource($template), 'Template updated');
    }

    public function publish(Request $request, int $id)
    {
        $template = $this->findInCompanyOrFail($request, TemplateDefinition::class, $id);
        $template->update(['status' => 'published', 'published_at' => now(), 'version' => $template->version + 1]);
        $this->auditLog->log($request->user(), 'templates', 'publish', $template);

        return $this->ok(new TemplateDefinitionResource($template), 'Template published');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, TemplateDefinition::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'published' => (clone $base)->where('status', 'published')->count(),
        ], 'Template metrics');
    }
}
