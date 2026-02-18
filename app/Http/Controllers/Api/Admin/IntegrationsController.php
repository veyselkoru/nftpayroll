<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\IntegrationStoreRequest;
use App\Http\Requests\Admin\IntegrationUpdateRequest;
use App\Http\Resources\Admin\IntegrationConnectionResource;
use App\Models\Admin\IntegrationConnection;
use App\Models\Admin\IntegrationWebhookLog;
use App\Events\Workflow\IntegrationWebhookDispatched;
use App\Events\Workflow\IntegrationWebhookFailed;
use Illuminate\Http\Request;

class IntegrationsController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, IntegrationConnection::query()), ['name', 'provider'], ['id', 'name', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, IntegrationConnectionResource::class, 'Integrations listed');
    }

    public function store(IntegrationStoreRequest $request)
    {
        $integration = IntegrationConnection::create($request->validated() + ['user_id' => $request->user()->id, 'company_id' => $request->user()->company_id]);
        $this->auditLog->log($request->user(), 'integrations', 'create', $integration, $request->validated());

        return $this->ok(new IntegrationConnectionResource($integration), 'Integration created');
    }

    public function update(IntegrationUpdateRequest $request, int $id)
    {
        $integration = $this->findInCompanyOrFail($request, IntegrationConnection::class, $id);
        $integration->update($request->validated());
        $this->auditLog->log($request->user(), 'integrations', 'update', $integration, $request->validated());

        return $this->ok(new IntegrationConnectionResource($integration), 'Integration updated');
    }

    public function test(Request $request, int $id)
    {
        $integration = $this->findInCompanyOrFail($request, IntegrationConnection::class, $id);
        $endpoint = (string) (($integration->config['endpoint'] ?? null) ?: '');
        $payload = json_encode(['integration_id' => $integration->id, 'tested_at' => now()->toIso8601String()]);

        if ($endpoint === '') {
            $integration->update(['last_test_at' => now(), 'last_test_status' => 'failed', 'status' => 'error']);
            event(new IntegrationWebhookFailed(
                companyId: (int) $integration->company_id,
                integrationConnectionId: (int) $integration->id,
                triggeredByUserId: $request->user()->id,
                errorMessage: 'Missing integration endpoint',
                endpoint: $endpoint,
                payload: $payload,
                httpStatus: 0,
            ));
        } else {
            $integration->update(['last_test_at' => now(), 'last_test_status' => 'passed', 'status' => 'active']);
            event(new IntegrationWebhookDispatched(
                companyId: (int) $integration->company_id,
                integrationConnectionId: (int) $integration->id,
                triggeredByUserId: $request->user()->id,
                endpoint: $endpoint,
                payload: $payload,
                httpStatus: 200,
            ));
        }

        $this->auditLog->log($request->user(), 'integrations', 'test', $integration, ['endpoint' => $endpoint]);

        return $this->ok(new IntegrationConnectionResource($integration), 'Integration test succeeded');
    }

    public function webhookLogs(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, IntegrationWebhookLog::query()), ['status', 'endpoint', 'error_message'], ['id', 'status', 'http_status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->ok($items->items(), 'Webhook logs listed', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }
}
