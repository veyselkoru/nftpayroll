<?php

namespace App\Events\Workflow;

class MintStarted
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $payrollId,
        public int $nftMintId,
    ) {
    }
}
