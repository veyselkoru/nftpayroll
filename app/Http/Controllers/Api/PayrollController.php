<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesCompany;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use App\Models\Admin\ApprovalRequest;
use App\Models\Admin\BulkOperationRun;
use App\Models\Admin\NotificationEvent;
use App\Models\Admin\WalletValidation;
use App\Services\PayrollEncryptionService;
use App\Jobs\MintPayrollNftJob;
use App\Events\Workflow\PayrollCreated;
use App\Events\Workflow\MintQueued;
use App\Events\Workflow\MintRetried;
use App\Events\Workflow\PayrollDecryptedViewed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
                'period_start'    => optional($p->period_start)->format('d-m-Y'),
                'period_end'      => optional($p->period_end)->format('d-m-Y'),
                'payment_date'    => optional($p->payment_date)->format('d-m-Y'),
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
            'national_id' => ['nullable', 'string', 'size:11'],
        ]);

        if (!empty($data['national_id'])) {
            if ($employee->national_id === null || $data['national_id'] !== $employee->national_id) {
                return response()->json([
                    'message' => 'national_id bu çalışanın TC kaydıyla uyuşmuyor.',
                ], 422);
            }
        }
        
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
            'company_id'         => $company->id,
            'employee_id'        => $employee->id,
            'template_id'        => null,
            'template_version'   => null,
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

        event(new PayrollCreated(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            triggeredByUserId: $request->user()->id,
        ));

        $this->queueMintForPayroll($employee, $payroll, $request->user()->id);
        return response()->json($payroll->fresh(), 201);
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
        $this->authorizeCompany($request->user(), $company);

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
     * Tek bir payroll için NftMint kaydı oluşturup kuyruğa atar.
     * Queue endpoint'inde kullandığın mantığın aynısı, ama HTTP response döndürmüyor.
     */
    protected function queueMintForPayroll(Employee $employee, Payroll $payroll, ?int $triggeredByUserId = null): ?NftMint
    {
        if (! $this->canQueueMint($payroll, $employee, $triggeredByUserId)) {
            return null;
        }

        $walletAddress = $employee->wallet_address ?: $this->defaultMintWalletAddress();

        $nftMint = $payroll->nftMint;

        // Zaten pending / processing / sent ise tekrar kuyruğa almıyoruz
        if ($nftMint && in_array($nftMint->status, ['pending', 'processing', 'sent'])) {
            return $nftMint;
        }

        // 3) NftMint kaydını oluştur veya resetle (PayrollQueueController'dakiyle aynı)
        if (! $nftMint) {
            $nftMint = NftMint::create([
                'payroll_id'     => $payroll->id,
                'company_id'     => $employee->company_id,
                'wallet_address' => $walletAddress,
                'status'         => 'pending',
                'error_message'  => null,
                'token_id'       => null,
                'tx_hash'        => null,
                'ipfs_cid'       => $payroll->ipfs_cid, // varsa
            ]);
        } else {
            $nftMint->update([
                'status'        => 'pending',
                'error_message' => null,
                'token_id'      => null,
                'tx_hash'       => null,
            ]);
        }

        // Payroll status: queued
        $payroll->update([
            'status' => 'queued',
        ]);

        // Job kuyruğa
        MintPayrollNftJob::dispatch($nftMint);

        event(new MintQueued(
            companyId: (int) $employee->company_id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            triggeredByUserId: $triggeredByUserId,
        ));

        return $nftMint;
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

        $nft = $payroll->nftMint;
        $topLevelStatus = $this->normalizeMintStatus($nft?->status ?: $payroll->status);

        return response()->json([
            'data' => [
                'payroll_id' => $payroll->id,
                'status' => $topLevelStatus,
                'token_id' => $nft?->token_id,
                'tx_hash' => $nft?->tx_hash,
                'ipfs_cid' => $nft?->ipfs_cid ?: $payroll->ipfs_cid,
                'updated_at' => optional($nft?->updated_at ?: $payroll->updated_at)?->toISOString(),
                'nft' => $nft ? [
                    'status' => $this->normalizeMintStatus($nft->status),
                    'token_id' => $nft->token_id,
                    'tx_hash' => $nft->tx_hash,
                    'ipfs_cid' => $nft->ipfs_cid,
                    'image_url' => $nft->image_url,
                ] : null,
            ],
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

        event(new MintRetried(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            triggeredByUserId: $request->user()->id,
        ));

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

            event(new PayrollDecryptedViewed(
                companyId: (int) $company->id,
                employeeId: (int) $employee->id,
                payrollId: (int) $payroll->id,
                triggeredByUserId: $request->user()->id,
            ));

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

    // app/Http/Controllers/Api/PayrollController.php

    public function bulkStore(
        Request $request,
        Company $company,
        Employee $employee,
        PayrollEncryptionService $encryptionService
    ) {
        // Çalışana erişim doğrulama
        $this->authorizeAccess($request, $company, $employee);
    
        $items = $request->input('items');
    
        if (!is_array($items)) {
            return response()->json([
                'message' => 'items alanı zorunlu ve dizi olmalıdır.',
            ], 422);
        }
    
        // İsteğe bağlı group id (kullanıcı göndermezse backend üretir)
        $groupId = $request->input('payroll_group_id') ?: (string) Str::uuid();
        $itemResults = [];
        $run = BulkOperationRun::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'name' => 'Employee Payroll Bulk Import',
            'type' => 'payroll_import',
            'status' => 'running',
            'total_items' => count($items),
            'processed_items' => 0,
            'failed_items' => 0,
            'payload' => ['employee_id' => $employee->id, 'payroll_group_id' => $groupId],
            'started_at' => now(),
        ]);
    
        $rules = [
            // 🔴 ÇALIŞAN TARAFINDA DA TC ZORUNLU
            'national_id'        => ['required', 'string', 'size:11'],
    
            'period_start'       => ['required', 'date'],
            'period_end'         => ['required', 'date', 'after_or_equal:period_start'],
            'payment_date'       => ['nullable', 'date'],
    
            'currency'           => ['nullable', 'string', 'max:10'],
    
            'gross_salary'       => ['required', 'numeric'],
            'net_salary'         => ['required', 'numeric'],
            'bonus'              => ['nullable', 'numeric'],
            'deductions_total'   => ['nullable', 'numeric'],
    
            'employer_sign_name'  => ['nullable', 'string', 'max:255'],
            'employer_sign_title' => ['nullable', 'string', 'max:255'],
    
            'batch_id'           => ['nullable', 'string', 'max:100'],
            'external_batch_ref' => ['nullable', 'string', 'max:100'],
            'external_ref'       => ['nullable', 'string', 'max:100'],
        ];
    
        $created = [];
        $failed  = [];
    
        foreach ($items as $index => $data) {
            if (!is_array($data)) {
                $failed[] = [
                    'index'  => $index,
                    'errors' => ['Geçersiz kayıt formatı.'],
                    'data'   => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => 'Geçersiz kayıt formatı', 'original_index' => $index];
                continue;
            }
    
            $validator = Validator::make($data, $rules);
    
            if ($validator->fails()) {
                $failed[] = [
                    'index'  => $index,
                    'errors' => $validator->errors()->all(),
                    'data'   => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => implode(', ', $validator->errors()->all()), 'original_index' => $index];
                continue;
            }
    
            $row = $validator->validated();
    
            // 🔴 TC GERÇEKTEN BU ÇALIŞANA MI AİT?
            if ($employee->national_id === null) {
                $failed[] = [
                    'index'  => $index,
                    'errors' => ['Sistemde bu çalışana ait kayıtlı bir national_id (TC) yok.'],
                    'data'   => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => 'Çalışana ait national_id yok', 'original_index' => $index];
                continue;
            }
    
            if ($row['national_id'] !== $employee->national_id) {
                $failed[] = [
                    'index'  => $index,
                    'errors' => ['Gönderilen national_id, bu çalışanın TC bilgisiyle uyuşmuyor.'],
                    'data'   => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => 'national_id uyuşmuyor', 'original_index' => $index];
                continue;
            }
    
            if (empty($row['currency'])) {
                $row['currency'] = 'TRY';
            }
    
            $payload = [
                'period_start'       => $row['period_start'],
                'period_end'         => $row['period_end'],
                'payment_date'       => $row['payment_date'] ?? null,
                'currency'           => $row['currency'],
                'gross_salary'       => $row['gross_salary'],
                'net_salary'         => $row['net_salary'],
                'bonus'              => $row['bonus'] ?? null,
                'deductions_total'   => $row['deductions_total'] ?? null,
                'employer_sign_name'  => $row['employer_sign_name'] ?? null,
                'employer_sign_title' => $row['employer_sign_title'] ?? null,
            ];
    
            $encryptedPayload = $encryptionService->encryptPayload($payload);
    
            try {
                $payroll = Payroll::create([
                    'company_id'         => $company->id,
                    'employee_id'        => $employee->id,
                    'template_id'        => null,
                    'template_version'   => null,
                    'payroll_group_id'   => $groupId,
                    'period_start'       => $row['period_start'],
                    'period_end'         => $row['period_end'],
                    'payment_date'       => $row['payment_date'] ?? null,
                    'currency'           => $row['currency'],
                    'gross_salary'       => $row['gross_salary'],
                    'net_salary'         => $row['net_salary'],
                    'bonus'              => $row['bonus'] ?? null,
                    'deductions_total'   => $row['deductions_total'] ?? null,
                    'employer_sign_name' => $row['employer_sign_name'] ?? null,
                    'employer_sign_title'=> $row['employer_sign_title'] ?? null,
                    'batch_id'           => $row['batch_id'] ?? null,
                    'external_batch_ref' => $row['external_batch_ref'] ?? null,
                    'external_ref'       => $row['external_ref'] ?? null,
                    'status'             => 'pending',
                    'encrypted_payload'  => $encryptedPayload,
                ]);
    
                // 🔁 Her payroll için otomatik mint kuyruğu
                event(new PayrollCreated(
                    companyId: (int) $company->id,
                    employeeId: (int) $employee->id,
                    payrollId: (int) $payroll->id,
                    triggeredByUserId: $request->user()->id,
                ));
                $this->queueMintForPayroll($employee, $payroll, $request->user()->id);
    
                $created[] = $payroll;
                $itemResults[] = ['index' => $index, 'status' => 'success', 'payroll_id' => $payroll->id, 'original_index' => $index];
            } catch (\Throwable $e) {
                $failed[] = [
                    'index'  => $index,
                    'errors' => [$e->getMessage()],
                    'data'   => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => $e->getMessage(), 'original_index' => $index];
            }
        }

        $run->update([
            'processed_items' => count($created),
            'failed_items' => count($failed),
            'results' => $itemResults,
            'status' => count($failed) > 0 ? (count($created) > 0 ? 'completed' : 'failed') : 'completed',
            'finished_at' => now(),
        ]);
    
        return response()->json([
            'bulk_operation_run_id' => $run->id,
            'payroll_group_id' => $groupId,
            'created_count'    => count($created),
            'failed_count'     => count($failed),
            'created'          => $created,
            'failed'           => $failed,
        ]);
    }

    public function bulkStoreForCompany(
        Request $request,
        Company $company,
        PayrollEncryptionService $encryptionService
    ) {
        $this->authorizeCompany($request->user(), $company);

        $items = $request->input('items');

        if (!is_array($items)) {
            return response()->json([
                'message' => 'items alanı zorunlu ve dizi olmalıdır.',
            ], 422);
        }

        $groupId = $request->input('payroll_group_id') ?: (string) Str::uuid();
        $itemResults = [];
        $run = BulkOperationRun::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'name' => 'Company Payroll Bulk Import',
            'type' => 'payroll_import',
            'status' => 'running',
            'total_items' => count($items),
            'processed_items' => 0,
            'failed_items' => 0,
            'payload' => ['payroll_group_id' => $groupId],
            'started_at' => now(),
        ]);

        $rules = [
            'employee' => ['required', 'array'],
            'employee.national_id' => ['required', 'string', 'size:11'],
            'employee.name' => ['nullable', 'string', 'max:255'],
            'employee.surname' => ['nullable', 'string', 'max:255'],
            'employee.full_name' => ['nullable', 'string', 'max:255'],
            'employee.employee_code' => ['nullable', 'string', 'max:50'],
            'employee.position' => ['nullable', 'string', 'max:100'],
            'employee.department' => ['nullable', 'string', 'max:100'],
            'employee.start_date' => ['nullable', 'date'],
            'employee.status' => ['nullable', 'string', 'in:active,inactive'],
            'employee.wallet_address' => ['nullable', 'string', 'max:42', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'payroll' => ['required', 'array'],
            'payroll.period_start' => ['required', 'date'],
            'payroll.period_end' => ['required', 'date', 'after_or_equal:payroll.period_start'],
            'payroll.payment_date' => ['nullable', 'date'],
            'payroll.currency' => ['nullable', 'string', 'max:10'],
            'payroll.gross_salary' => ['required', 'numeric'],
            'payroll.net_salary' => ['required', 'numeric'],
            'payroll.bonus' => ['nullable', 'numeric'],
            'payroll.deductions_total' => ['nullable', 'numeric'],
            'payroll.employer_sign_name' => ['nullable', 'string', 'max:255'],
            'payroll.employer_sign_title' => ['nullable', 'string', 'max:255'],
            'payroll.batch_id' => ['nullable', 'string', 'max:100'],
            'payroll.external_batch_ref' => ['nullable', 'string', 'max:100'],
            'payroll.external_ref' => ['nullable', 'string', 'max:100'],
        ];

        $created = [];
        $failed = [];
        $createdEmployees = [];

        foreach ($items as $index => $data) {
            if (!is_array($data)) {
                $failed[] = [
                    'index' => $index,
                    'errors' => ['Geçersiz kayıt formatı.'],
                    'data' => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => 'Geçersiz kayıt formatı', 'original_index' => $index];
                continue;
            }

            $normalizedItem = $this->normalizeCompanyBulkItem($data);
            $validator = Validator::make($normalizedItem, $rules);

            if ($validator->fails()) {
                $failed[] = [
                    'index' => $index,
                    'errors' => $validator->errors()->all(),
                    'data' => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => implode(', ', $validator->errors()->all()), 'original_index' => $index];
                continue;
            }

            $row = $validator->validated();
            $employeeData = $row['employee'];
            $payrollData = $row['payroll'];

            $employee = Employee::where('company_id', $company->id)
                ->where('national_id', $employeeData['national_id'])
                ->first();

            if (! $employee) {
                [$firstName, $surname] = $this->resolveEmployeeName(
                    $employeeData['name'] ?? null,
                    $employeeData['surname'] ?? null,
                    $employeeData['full_name'] ?? null
                );

                if ($firstName === null || $surname === null) {
                    $failed[] = [
                        'index' => $index,
                        'errors' => ['Yeni çalışan için employee.name + employee.surname veya employee.full_name zorunludur.'],
                        'data' => $data,
                    ];
                    $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => 'Yeni çalışan adı/soyadı eksik', 'original_index' => $index];
                    continue;
                }

                $employee = Employee::create([
                    'company_id' => $company->id,
                    'national_id' => $employeeData['national_id'],
                    'name' => $firstName,
                    'surname' => $surname,
                    'employee_code' => $employeeData['employee_code'] ?? null,
                    'position' => $employeeData['position'] ?? null,
                    'department' => $employeeData['department'] ?? null,
                    'start_date' => $employeeData['start_date'] ?? null,
                    'status' => $employeeData['status'] ?? 'active',
                    'wallet_address' => $employeeData['wallet_address'] ?? $this->defaultMintWalletAddress(),
                ]);

                $createdEmployees[] = [
                    'id' => $employee->id,
                    'national_id' => $employee->national_id,
                    'name' => $employee->name,
                    'surname' => $employee->surname,
                ];
            } elseif (empty($employee->wallet_address)) {
                if (!empty($employeeData['wallet_address'])) {
                    $employee->update([
                        'wallet_address' => $employeeData['wallet_address'],
                    ]);
                    $employee->refresh();
                } else {
                    $employee->update([
                        'wallet_address' => $this->defaultMintWalletAddress(),
                    ]);
                    $employee->refresh();
                }
            }

            if (empty($payrollData['currency'])) {
                $payrollData['currency'] = 'TRY';
            }

            $payload = [
                'period_start' => $payrollData['period_start'],
                'period_end' => $payrollData['period_end'],
                'payment_date' => $payrollData['payment_date'] ?? null,
                'currency' => $payrollData['currency'],
                'gross_salary' => $payrollData['gross_salary'],
                'net_salary' => $payrollData['net_salary'],
                'bonus' => $payrollData['bonus'] ?? null,
                'deductions_total' => $payrollData['deductions_total'] ?? null,
                'employer_sign_name' => $payrollData['employer_sign_name'] ?? null,
                'employer_sign_title' => $payrollData['employer_sign_title'] ?? null,
            ];

            $encryptedPayload = $encryptionService->encryptPayload($payload);

            try {
                $payroll = Payroll::create([
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'template_id' => null,
                    'template_version' => null,
                    'payroll_group_id' => $groupId,
                    'period_start' => $payrollData['period_start'],
                    'period_end' => $payrollData['period_end'],
                    'payment_date' => $payrollData['payment_date'] ?? null,
                    'currency' => $payrollData['currency'],
                    'gross_salary' => $payrollData['gross_salary'],
                    'net_salary' => $payrollData['net_salary'],
                    'bonus' => $payrollData['bonus'] ?? null,
                    'deductions_total' => $payrollData['deductions_total'] ?? null,
                    'employer_sign_name' => $payrollData['employer_sign_name'] ?? null,
                    'employer_sign_title' => $payrollData['employer_sign_title'] ?? null,
                    'batch_id' => $payrollData['batch_id'] ?? null,
                    'external_batch_ref' => $payrollData['external_batch_ref'] ?? null,
                    'external_ref' => $payrollData['external_ref'] ?? null,
                    'status' => 'pending',
                    'encrypted_payload' => $encryptedPayload,
                ]);

                event(new PayrollCreated(
                    companyId: (int) $company->id,
                    employeeId: (int) $employee->id,
                    payrollId: (int) $payroll->id,
                    triggeredByUserId: $request->user()->id,
                ));
                $this->queueMintForPayroll($employee, $payroll, $request->user()->id);

                $created[] = $payroll;
                $itemResults[] = ['index' => $index, 'status' => 'success', 'payroll_id' => $payroll->id, 'original_index' => $index];
            } catch (\Throwable $e) {
                $failed[] = [
                    'index' => $index,
                    'errors' => [$e->getMessage()],
                    'data' => $data,
                ];
                $itemResults[] = ['index' => $index, 'status' => 'failed', 'reason' => $e->getMessage(), 'original_index' => $index];
            }
        }

        $run->update([
            'processed_items' => count($created),
            'failed_items' => count($failed),
            'results' => $itemResults,
            'status' => count($failed) > 0 ? (count($created) > 0 ? 'completed' : 'failed') : 'completed',
            'finished_at' => now(),
        ]);

        return response()->json([
            'bulk_operation_run_id' => $run->id,
            'payroll_group_id' => $groupId,
            'created_count' => count($created),
            'created_employees_count' => count($createdEmployees),
            'created_employees' => $createdEmployees,
            'failed_count' => count($failed),
            'created' => $created,
            'failed' => $failed,
        ]);
    }

    protected function canQueueMint(Payroll $payroll, Employee $employee, ?int $triggeredByUserId = null): bool
    {
        $wallet = $employee->wallet_address ?: $this->defaultMintWalletAddress();
        $isValidWallet = (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', (string) $wallet);

        WalletValidation::create([
            'company_id' => $employee->company_id,
            'user_id' => $triggeredByUserId,
            'wallet_address' => (string) $wallet,
            'network' => 'sepolia',
            'status' => $isValidWallet ? 'valid' : 'invalid',
            'message' => $isValidWallet ? 'Wallet validated before queue' : 'Invalid wallet before queue',
            'checked_at' => now(),
        ]);

        if (! $isValidWallet && ! filter_var(env('WALLET_POLICY_OVERRIDE', false), FILTER_VALIDATE_BOOL)) {
            NotificationEvent::create([
                'company_id' => $employee->company_id,
                'user_id' => $triggeredByUserId,
                'title' => 'Invalid wallet blocked mint queue',
                'body' => 'Wallet validation failed before queue',
                'channel' => 'in_app',
                'status' => 'queued',
                'is_read' => false,
                'payload' => ['employee_id' => $employee->id, 'payroll_id' => $payroll->id],
            ]);

            $payroll->update(['status' => 'wallet_invalid']);
            return false;
        }

        if (filter_var(env('MINT_APPROVAL_REQUIRED', false), FILTER_VALIDATE_BOOL)) {
            $approval = ApprovalRequest::query()
                ->where('payroll_id', $payroll->id)
                ->where('type', 'mint_approval')
                ->latest('id')
                ->first();

            if (! $approval) {
                $approval = ApprovalRequest::create([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'payroll_id' => $payroll->id,
                    'user_id' => $triggeredByUserId,
                    'title' => 'Mint approval required',
                    'type' => 'mint_approval',
                    'policy_key' => 'mint.approval.required',
                    'status' => 'pending',
                    'payload' => ['payroll_id' => $payroll->id, 'employee_id' => $employee->id],
                ]);
            }

            if ($approval->status !== 'approved') {
                $payroll->update(['status' => 'awaiting_approval']);
                return false;
            }
        }

        return true;
    }

    protected function normalizeCompanyBulkItem(array $data): array
    {
        if (isset($data['employee']) || isset($data['payroll'])) {
            return $data;
        }

        return [
            'employee' => [
                'national_id' => $data['national_id'] ?? null,
                'name' => $data['name'] ?? null,
                'surname' => $data['surname'] ?? null,
                'full_name' => $data['full_name'] ?? null,
                'employee_code' => $data['employee_code'] ?? null,
                'position' => $data['position'] ?? null,
                'department' => $data['department'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'status' => $data['status'] ?? null,
                'wallet_address' => $data['wallet_address'] ?? null,
            ],
            'payroll' => $data,
        ];
    }

    protected function resolveEmployeeName(?string $name, ?string $surname, ?string $fullName): array
    {
        $name = $name !== null ? trim($name) : null;
        $surname = $surname !== null ? trim($surname) : null;
        $fullName = $fullName !== null ? trim(preg_replace('/\s+/', ' ', $fullName)) : null;

        if ($name && $surname) {
            return [$name, $surname];
        }

        if ($fullName) {
            $parts = explode(' ', $fullName);
            $lastName = array_pop($parts);
            $firstName = trim(implode(' ', $parts));

            if ($firstName !== '' && $lastName !== '') {
                return [$firstName, $lastName];
            }
        }

        return [null, null];
    }

    protected function defaultMintWalletAddress(): string
    {
        return (string) env('DEFAULT_MINT_WALLET', '0x125E82e69A4b499315806b10b9678f3CDE6B977E');
    }

    protected function normalizeMintStatus(?string $status): ?string
    {
        return match ($status) {
            'minted' => 'sent',
            'running' => 'processing',
            default => $status,
        };
    }

}
