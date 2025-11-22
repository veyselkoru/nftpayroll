<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use App\Services\PayrollEncryptionService;
use App\Jobs\MintPayrollNftJob;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * Liste
     * GET /companies/{company}/employees/{employee}/payrolls
     */
    public function index(Request $request, Company $company, Employee $employee)
    {
        $this->authorizeAccess($request, $company, $employee);

        $payrolls = $employee->payrolls()
            ->with('nftMint')
            ->orderByDesc('id')
            ->get()
            ->map(function (Payroll $payroll) {
                return [
                    'id'           => $payroll->id,
                    'period_start' => $payroll->period_start,
                    'period_end'   => $payroll->period_end,
                    'gross_salary' => $payroll->gross_salary,
                    'net_salary'   => $payroll->net_salary,
                    'status'       => $payroll->status,

                    // Küçük NFT özeti (frontend listede badge vs. için)
                    'nft' => $payroll->nftMint ? [
                        'status'   => $payroll->nftMint->status,
                        'token_id' => $payroll->nftMint->token_id,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $payrolls,
        ]);
    }

    /**
     * Oluştur
     * POST /companies/{company}/employees/{employee}/payrolls
     */
    public function store(
        Request $request,
        Company $company,
        Employee $employee,
        PayrollEncryptionService $encryptionService
    ) {
        $this->authorizeAccess($request, $company, $employee);

        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date',
            'gross_salary' => 'required|numeric',
            'net_salary'   => 'required|numeric'
        ]);

        $payload = [
            'period_start' => $data['period_start'],
            'period_end'   => $data['period_end'],
            'gross_salary' => $data['gross_salary'],
            'net_salary'   => $data['net_salary'],
        ];
        
        $encryptedPayload = $encryptionService->encryptPayload($payload);

        $payroll = Payroll::create([
            'employee_id'       => $employee->id,
            'period_start'      => $data['period_start'],
            'period_end'        => $data['period_end'],
            'gross_salary'      => $data['gross_salary'],
            'net_salary'        => $data['net_salary'],
            'status'            => 'pending',
            'encrypted_payload' => $encryptedPayload,
        ]);

        return response()->json($payroll, 201);
    }

    /**
     * Detay
     * GET /companies/{company}/employees/{employee}/payrolls/{payroll}
     */
    public function show(
        Request $request,
        Company $company,
        Employee $employee,
        Payroll $payroll
    ) {
        $this->authorizeAccess($request, $company, $employee);
        $this->ensurePayrollBelongsToEmployee($payroll, $employee);

        $payroll->load(['employee', 'nftMint']);

        return response()->json([
            'payroll' => $payroll,
        ]);
    }

    /**
     * Ortak erişim kontrolü:
     * - Şirket sahibinin user id'si ile login user eşleşiyor mu?
     * - Employee gerçekten bu şirkete mi ait?
     */
    protected function authorizeAccess(Request $request, Company $company, Employee $employee)
    {
        if ($company->owner_id !== $request->user()->id) {
            abort(403, 'Yetkisiz');
        }

        if ($employee->company_id !== $company->id) {
            abort(404, 'Çalışan bu şirkete ait değil');
        }
    }

    /**
     * Payroll gerçekten bu employee'ye mi ait?
     */
    protected function ensurePayrollBelongsToEmployee(Payroll $payroll, Employee $employee)
    {
        if ($payroll->employee_id !== $employee->id) {
            abort(404, 'Bordro bu çalışana ait değil');
        }
    }

    /**
     * Durum endpoint'i
     * GET /companies/{company}/employees/{employee}/payrolls/{payroll}/status
     */
    public function status(
        Request $request,
        Company $company,
        Employee $employee,
        Payroll $payroll
    ) {
        $this->authorizeAccess($request, $company, $employee);
        $this->ensurePayrollBelongsToEmployee($payroll, $employee);

        $payroll->load('nftMint');

        return response()->json([
            'payroll_id' => $payroll->id,
            'status'     => $payroll->status,
            'nft'        => $payroll->nftMint ? [
                'id'           => $payroll->nftMint->id,
                'status'       => $payroll->nftMint->status,
                'token_id'     => $payroll->nftMint->token_id,
                'tx_hash'      => $payroll->nftMint->tx_hash,
                'ipfs_cid'     => $payroll->nftMint->ipfs_cid,
                'ipfs_url'     => $payroll->nftMint->ipfs_cid
                    ? 'https://ipfs.io/ipfs/'.$payroll->nftMint->ipfs_cid
                    : null,
                'explorer_url' => $payroll->nftMint->tx_hash
                    ? 'https://sepolia.etherscan.io/tx/'.$payroll->nftMint->tx_hash
                    : null,
            ] : null,
        ]);
    }

    /**
     * Mint retry endpoint'i
     * POST /companies/{company}/employees/{employee}/payrolls/{payroll}/mint/retry
     */
    public function retryMint(
        Request $request,
        Company $company,
        Employee $employee,
        Payroll $payroll
    ) {
        $this->authorizeAccess($request, $company, $employee);
        $this->ensurePayrollBelongsToEmployee($payroll, $employee);

        $nftMint = $payroll->nftMint;

        if (! $nftMint) {
            return response()->json([
                'message' => 'Bu payroll için NFT mint kaydı yok.',
            ], 404);
        }

        if ($nftMint->status === 'sent') {
            return response()->json([
                'message' => 'NFT zaten minted.',
            ], 422);
        }

        // status'i resetle, hata mesajını temizle
        $nftMint->update([
            'status'        => 'pending',
            'error_message' => null,
            'tx_hash'       => null,
            'token_id'      => null,
        ]);

        // job tekrar kuyruğa
        MintPayrollNftJob::dispatch($nftMint);

        return response()->json([
            'message'     => 'Mint retry kuyruğa alındı.',
            'nft_mint_id' => $nftMint->id,
        ]);
    }
}
