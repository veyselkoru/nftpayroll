<?php

namespace App\Events\Workflow;

class PayrollCreated
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $payrollId,
        public ?int $triggeredByUserId = null,
    ) {
    }
}
