<?php

namespace App\Events\Workflow;

class MintQueued
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $payrollId,
        public ?int $nftMintId,
        public ?int $triggeredByUserId = null,
    ) {
    }
}
