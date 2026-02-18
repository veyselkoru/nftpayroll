<?php

namespace App\Providers;

use App\Events\Workflow\ExportRequested;
use App\Events\Workflow\IntegrationWebhookDispatched;
use App\Events\Workflow\IntegrationWebhookFailed;
use App\Events\Workflow\MintFailed;
use App\Events\Workflow\MintQueued;
use App\Events\Workflow\MintRetried;
use App\Events\Workflow\MintStarted;
use App\Events\Workflow\MintSucceeded;
use App\Events\Workflow\PayrollCreated;
use App\Events\Workflow\PayrollDecryptedViewed;
use App\Listeners\Workflow\ProjectWorkflowListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PayrollCreated::class => [
            [ProjectWorkflowListener::class, 'onPayrollCreated'],
        ],
        MintQueued::class => [
            [ProjectWorkflowListener::class, 'onMintQueued'],
        ],
        MintStarted::class => [
            [ProjectWorkflowListener::class, 'onMintStarted'],
        ],
        MintSucceeded::class => [
            [ProjectWorkflowListener::class, 'onMintSucceeded'],
        ],
        MintFailed::class => [
            [ProjectWorkflowListener::class, 'onMintFailed'],
        ],
        MintRetried::class => [
            [ProjectWorkflowListener::class, 'onMintRetried'],
        ],
        PayrollDecryptedViewed::class => [
            [ProjectWorkflowListener::class, 'onPayrollDecryptedViewed'],
        ],
        ExportRequested::class => [
            [ProjectWorkflowListener::class, 'onExportRequested'],
        ],
        IntegrationWebhookDispatched::class => [
            [ProjectWorkflowListener::class, 'onIntegrationWebhookDispatched'],
        ],
        IntegrationWebhookFailed::class => [
            [ProjectWorkflowListener::class, 'onIntegrationWebhookFailed'],
        ],
    ];
}
