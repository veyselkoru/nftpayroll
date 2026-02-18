<?php

namespace App\Events\Workflow;

class MintSucceeded
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $payrollId,
        public int $nftMintId,
        public ?string $txHash,
        public $tokenId,
        public ?int $durationMs = null,
    ) {
    }
}
