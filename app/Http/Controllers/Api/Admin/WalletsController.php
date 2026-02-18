<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\WalletBulkValidateRequest;
use App\Http\Requests\Admin\WalletValidateRequest;
use App\Http\Resources\Admin\WalletValidationResource;
use App\Models\Admin\WalletValidation;
use Illuminate\Http\Request;

class WalletsController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, WalletValidation::query()), ['wallet_address', 'network'], ['id', 'wallet_address', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, WalletValidationResource::class, 'Wallet validations listed');
    }

    public function validateWallet(WalletValidateRequest $request)
    {
        $data = $request->validated();
        $isValid = preg_match('/^0x[0-9a-fA-F]{40}$/', $data['wallet_address']) === 1;

        $row = WalletValidation::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'wallet_address' => $data['wallet_address'],
            'network' => $data['network'] ?? 'sepolia',
            'status' => $isValid ? 'valid' : 'invalid',
            'message' => $isValid ? 'Wallet format is valid' : 'Invalid wallet format',
            'checked_at' => now(),
        ]);

        $this->auditLog->log($request->user(), 'wallets', 'validate', $row, $data);

        return $this->ok(new WalletValidationResource($row), 'Wallet validation completed');
    }

    public function bulkValidate(WalletBulkValidateRequest $request)
    {
        $created = [];

        foreach ($request->validated('items') as $item) {
            $isValid = preg_match('/^0x[0-9a-fA-F]{40}$/', $item['wallet_address']) === 1;
            $created[] = WalletValidation::create([
                'company_id' => $request->user()->company_id,
                'user_id' => $request->user()->id,
                'wallet_address' => $item['wallet_address'],
                'network' => $item['network'] ?? 'sepolia',
                'status' => $isValid ? 'valid' : 'invalid',
                'message' => $isValid ? 'Wallet format is valid' : 'Invalid wallet format',
                'checked_at' => now(),
            ]);
        }

        $this->auditLog->log($request->user(), 'wallets', 'bulk_validate', null, ['count' => count($created)]);

        return $this->ok(WalletValidationResource::collection(collect($created)), 'Bulk validation completed', ['count' => count($created)]);
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, WalletValidation::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'valid' => (clone $base)->where('status', 'valid')->count(),
            'invalid' => (clone $base)->where('status', 'invalid')->count(),
        ], 'Wallet metrics');
    }
}
