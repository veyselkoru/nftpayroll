<?php

namespace App\Events\Workflow;

class ExportRequested
{
    public function __construct(
        public int $companyId,
        public int $exportJobId,
        public ?int $triggeredByUserId = null,
    ) {
    }
}
