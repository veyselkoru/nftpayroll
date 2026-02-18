<?php

namespace App\OpenApi;

/**
 * @OA\Tag(name="Operations Center")
 * @OA\Tag(name="Approval Flows")
 * @OA\Tag(name="Compliance")
 * @OA\Tag(name="Notifications")
 * @OA\Tag(name="Integrations")
 * @OA\Tag(name="Templates")
 * @OA\Tag(name="Wallets")
 * @OA\Tag(name="Bulk Operations")
 * @OA\Tag(name="Cost Reports")
 * @OA\Tag(name="Roles")
 * @OA\Tag(name="Exports")
 * @OA\Tag(name="System Health")
 */
class AdminModulesDocumentation
{
    /** @OA\Get(path="/api/operations/jobs", tags={"Operations Center"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function operationsJobs() {}
    /** @OA\Post(path="/api/operations/jobs/{id}/retry", tags={"Operations Center"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function operationsRetry() {}
    /** @OA\Post(path="/api/operations/jobs/{id}/cancel", tags={"Operations Center"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function operationsCancel() {}
    /** @OA\Get(path="/api/operations/metrics", tags={"Operations Center"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function operationsMetrics() {}

    /** @OA\Get(path="/api/approvals", tags={"Approval Flows"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function approvalsIndex() {}
    /** @OA\Post(path="/api/approvals/{id}/approve", tags={"Approval Flows"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function approvalsApprove() {}
    /** @OA\Post(path="/api/approvals/{id}/reject", tags={"Approval Flows"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function approvalsReject() {}
    /** @OA\Get(path="/api/approvals/metrics", tags={"Approval Flows"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function approvalsMetrics() {}

    /** @OA\Get(path="/api/compliance/audit-logs", tags={"Compliance"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function complianceAuditLogs() {}
    /** @OA\Get(path="/api/compliance/security-events", tags={"Compliance"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function complianceSecurityEvents() {}
    /** @OA\Get(path="/api/compliance/export-history", tags={"Compliance"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function complianceExportHistory() {}

    /** @OA\Get(path="/api/notifications", tags={"Notifications"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function notificationsIndex() {}
    /** @OA\Post(path="/api/notifications/{id}/read", tags={"Notifications"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function notificationsRead() {}
    /** @OA\Post(path="/api/notifications/read-all", tags={"Notifications"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function notificationsReadAll() {}
    /** @OA\Get(path="/api/notifications/metrics", tags={"Notifications"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function notificationsMetrics() {}

    /** @OA\Get(path="/api/integrations", tags={"Integrations"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function integrationsIndex() {}
    /** @OA\Post(path="/api/integrations", tags={"Integrations"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function integrationsStore() {}
    /** @OA\Put(path="/api/integrations/{id}", tags={"Integrations"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function integrationsUpdate() {}
    /** @OA\Post(path="/api/integrations/{id}/test", tags={"Integrations"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function integrationsTest() {}
    /** @OA\Get(path="/api/integrations/webhooks/logs", tags={"Integrations"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function integrationsWebhookLogs() {}

    /** @OA\Get(path="/api/templates", tags={"Templates"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function templatesIndex() {}
    /** @OA\Post(path="/api/templates", tags={"Templates"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function templatesStore() {}
    /** @OA\Put(path="/api/templates/{id}", tags={"Templates"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function templatesUpdate() {}
    /** @OA\Post(path="/api/templates/{id}/publish", tags={"Templates"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function templatesPublish() {}
    /** @OA\Get(path="/api/templates/metrics", tags={"Templates"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function templatesMetrics() {}

    /** @OA\Get(path="/api/wallets", tags={"Wallets"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function walletsIndex() {}
    /** @OA\Post(path="/api/wallets/validate", tags={"Wallets"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function walletsValidate() {}
    /** @OA\Post(path="/api/wallets/bulk-validate", tags={"Wallets"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function walletsBulkValidate() {}
    /** @OA\Get(path="/api/wallets/metrics", tags={"Wallets"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function walletsMetrics() {}

    /** @OA\Get(path="/api/bulk-operations", tags={"Bulk Operations"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function bulkOpsIndex() {}
    /** @OA\Post(path="/api/bulk-operations/import", tags={"Bulk Operations"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function bulkOpsImport() {}
    /** @OA\Post(path="/api/bulk-operations/{id}/retry", tags={"Bulk Operations"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function bulkOpsRetry() {}
    /** @OA\Get(path="/api/bulk-operations/metrics", tags={"Bulk Operations"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function bulkOpsMetrics() {}

    /** @OA\Get(path="/api/cost-reports/summary", tags={"Cost Reports"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function costSummary() {}
    /** @OA\Get(path="/api/cost-reports/by-company", tags={"Cost Reports"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function costByCompany() {}
    /** @OA\Get(path="/api/cost-reports/by-network", tags={"Cost Reports"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function costByNetwork() {}

    /** @OA\Get(path="/api/roles", tags={"Roles"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function rolesIndex() {}
    /** @OA\Post(path="/api/roles", tags={"Roles"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function rolesStore() {}
    /** @OA\Put(path="/api/roles/{id}", tags={"Roles"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function rolesUpdate() {}
    /** @OA\Post(path="/api/roles/{id}/assign-users", tags={"Roles"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function rolesAssignUsers() {}
    /** @OA\Get(path="/api/roles/metrics", tags={"Roles"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function rolesMetrics() {}

    /** @OA\Get(path="/api/exports", tags={"Exports"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function exportsIndex() {}
    /** @OA\Post(path="/api/exports", tags={"Exports"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function exportsStore() {}
    /** @OA\Get(path="/api/exports/{id}/download", tags={"Exports"}, security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="OK")) */
    public function exportsDownload() {}
    /** @OA\Get(path="/api/exports/metrics", tags={"Exports"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function exportsMetrics() {}

    /** @OA\Get(path="/api/system-health/overview", tags={"System Health"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function systemOverview() {}
    /** @OA\Get(path="/api/system-health/services", tags={"System Health"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function systemServices() {}
    /** @OA\Get(path="/api/system-health/incidents", tags={"System Health"}, security={{"sanctum":{}}}, @OA\Response(response=200, description="OK")) */
    public function systemIncidents() {}
}
