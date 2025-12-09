<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesCompany;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use App\Services\PayrollEncryptionService;
use App\Jobs\MintPayrollNftJob;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    use AuthorizesCompany;
    /**
     * Liste
     * GET /companies/{company}/employees/{employee}/payrolls
     */
    public function index(Request $request, Company $company, Employee $employee)
    {
        $this->authorizeEmployee($request->user(), $company, $employee);

        $payrolls = Payroll::where('employee_id', $employee->id)
            ->orderBy('id', 'desc')
            ->with('nftMint') // eğer ilişki varsa
            ->get();

        // encrypted_payload HİÇ DÖNMEYECEK
        $sanitized = $payrolls->map(function (Payroll $p) {
            return [
                'id'              => $p->id,
                'employee_id'     => $p->employee_id,
                'period_start'    => optional($p->period_start)->toDateString(),
                'period_end'      => optional($p->period_end)->toDateString(),
                'payment_date'    => optional($p->payment_date)->toDateString(),
                'currency'        => $p->currency,
                'gross_salary'    => $p->gross_salary,
                'net_salary'      => $p->net_salary,
                'bonus'           => $p->bonus,
                'deductions_total'=> $p->deductions_total,
                'employer_sign_name'  => $p->employer_sign_name,
                'employer_sign_title' => $p->employer_sign_title,
                'status'          => $p->status,
                'ipfs_cid'        => $p->ipfs_cid,
                'tx_hash'         => $p->tx_hash,
                'nft' => $p->nftMint ? [
                    'status'   => $p->nftMint->status,
                    'ipfs_cid' => $p->nftMint->ipfs_cid,
                    'tx_hash'  => $p->nftMint->tx_hash,
                    'token_id' => $p->nftMint->token_id,
                    'image_url'=> $p->nftMint->image_url,
                ] : null,
                // 'encrypted_payload' YOK
            ];
        });

        return response()->json($sanitized);
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
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'payment_date' => ['nullable', 'date'],

            'currency'     => ['nullable', 'string', 'max:10'],

            'gross_salary' => ['required', 'numeric'],
            'net_salary'   => ['required', 'numeric'],
            'bonus'        => ['nullable', 'numeric'],
            'deductions_total' => ['nullable', 'numeric'],

            'employer_sign_name'  => ['nullable', 'string', 'max:255'],
            'employer_sign_title' => ['nullable', 'string', 'max:255'],

            'batch_id'           => ['nullable', 'string', 'max:100'],
            'external_batch_ref' => ['nullable', 'string', 'max:100'],
            'external_ref'       => ['nullable', 'string', 'max:100'],
        ]);

        // Varsayılan para birimi
        if (empty($data['currency'])) {
            $data['currency'] = 'TRY';
        }

        // 🔐 ŞİFRELENECEK PAYLOAD (eski key'ler + yeni alanlar)
        $payload = [
            'period_start'     => $data['period_start'],
            'period_end'       => $data['period_end'],
            'payment_date'     => $data['payment_date'] ?? null,
            'currency'         => $data['currency'],
            'gross_salary'     => $data['gross_salary'],
            'net_salary'       => $data['net_salary'],
            'bonus'            => $data['bonus'] ?? null,
            'deductions_total' => $data['deductions_total'] ?? null,
            'employer_sign_name'  => $data['employer_sign_name'] ?? null,
            'employer_sign_title' => $data['employer_sign_title'] ?? null,
        ];

        $encryptedPayload = $encryptionService->encryptPayload($payload);

        // 📄 Bordroyu oluştur
        $payroll = Payroll::create([
            'employee_id'        => $employee->id,
            'period_start'       => $data['period_start'],
            'period_end'         => $data['period_end'],
            'payment_date'       => $data['payment_date'] ?? null,
            'currency'           => $data['currency'],
            'gross_salary'       => $data['gross_salary'],
            'net_salary'         => $data['net_salary'],
            'bonus'              => $data['bonus'] ?? null,
            'deductions_total'   => $data['deductions_total'] ?? null,
            'employer_sign_name' => $data['employer_sign_name'] ?? null,
            'employer_sign_title'=> $data['employer_sign_title'] ?? null,
            'batch_id'           => $data['batch_id'] ?? null,
            'external_batch_ref' => $data['external_batch_ref'] ?? null,
            'external_ref'       => $data['external_ref'] ?? null,
            'status'             => 'pending',
            'encrypted_payload'  => $encryptedPayload,
        ]);

        return response()->json($payroll, 201);
    }

    public function update(Request $request, Company $company, Employee $employee, Payroll $payroll)
    {
        $this->authorizeAccess($request, $company, $employee);

        $data = $request->validate([
            'period_start' => ['sometimes', 'required', 'date'],
            'period_end'   => ['sometimes', 'required', 'date', 'after_or_equal:period_start'],
            'payment_date' => ['sometimes', 'nullable', 'date'],

            'currency'     => ['sometimes', 'nullable', 'string', 'max:10'],

            'gross_salary' => ['sometimes', 'required', 'numeric'],
            'net_salary'   => ['sometimes', 'required', 'numeric'],
            'bonus'        => ['sometimes', 'nullable', 'numeric'],
            'deductions_total' => ['sometimes', 'nullable', 'numeric'],

            'employer_sign_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'employer_sign_title' => ['sometimes', 'nullable', 'string', 'max:255'],

            'batch_id'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'external_batch_ref'=> ['sometimes', 'nullable', 'string', 'max:100'],
            'external_ref'      => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $payroll->update($data);

        return response()->json($payroll);
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

    public function decryptPayload(
        Request $request,
        Company $company,
        Employee $employee,
        Payroll $payroll,
        PayrollEncryptionService $encryptionService
    ) {
        $this->authorizeAccess($request, $company, $employee);
        $this->ensurePayrollBelongsToEmployee($payroll, $employee);

        if (! $payroll->encrypted_payload) {
            return response()->json([
                'message' => 'No encrypted payroll data found.'
            ], 404);
        }

        try {
            // 🔐 KENDİ SERVİSİNLE ÇÖZ
            $decrypted = $encryptionService->decryptPayload($payroll->encrypted_payload);

            return response()->json([
                'payroll_id'        => $payroll->id,
                'employee_id'       => $employee->id,
                'decrypted_payload' => $decrypted,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to decrypt payload.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    
}
