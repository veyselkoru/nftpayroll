<?php

namespace App\Events\Workflow;

class IntegrationWebhookDispatched
{
    public function __construct(
        public int $companyId,
        public int $integrationConnectionId,
        public ?int $triggeredByUserId = null,
        public ?string $endpoint = null,
        public ?string $payload = null,
        public ?int $httpStatus = null,
    ) {
    }
}
