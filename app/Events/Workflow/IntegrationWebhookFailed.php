<?php

namespace App\Events\Workflow;

class IntegrationWebhookFailed
{
    public function __construct(
        public int $companyId,
        public int $integrationConnectionId,
        public ?int $triggeredByUserId = null,
        public string $errorMessage = 'Webhook dispatch failed',
        public ?string $endpoint = null,
        public ?string $payload = null,
        public ?int $httpStatus = null,
    ) {
    }
}
