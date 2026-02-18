<?php

namespace App\Events\Workflow;

class MintFailed
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $payrollId,
        public ?int $nftMintId,
        public string $errorMessage,
    ) {
    }
}
