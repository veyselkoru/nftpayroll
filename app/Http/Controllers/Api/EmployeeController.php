<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesCompany;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use AuthorizesCompany;
    public function index(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $employees = $company->employees()->get();

        return response()->json($employees);
    }

    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $data = $request->validate([
            'employee_code'  => 'nullable|string|max:50',
            'name'           => 'required|string|max:255',
            'tc_no'          => 'nullable|string|min:11|max:11',
            'position'       => 'nullable|string|max:100',
            'department'     => 'nullable|string|max:100',
            'start_date'     => 'nullable|date',
            'status'         => 'nullable|string|in:active,inactive',
            'wallet_address' => [
                'nullable',
                'string',
                'max:42',
                'regex:/^0x[0-9a-fA-F]{40}$/',
            ],
        ], [
            'tc_no.min' => 'TC Kimlik No 11 haneli olmalıdır.',
            'tc_no.max' => 'TC Kimlik No 11 haneli olmalıdır.',
            'wallet_address.regex' => 'Wallet adresi geçerli bir Ethereum adresi olmalıdır.',
        ]);

        // status gönderilmediyse default aktif
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        $fullName = trim($request->input('name')); // "samet mehmet altın"
        $fullName = preg_replace('/\s+/', ' ', $fullName);
        $parts = explode(' ', $fullName);
        $surname = array_pop($parts);    // Son eleman soyad
        $name = implode(' ', $parts);    // Geriye kalanlar ad

        $data['name'] = $name;
        $data['surname'] = $surname;


        $employee = Employee::create([
            'company_id' => $company->id,
        ] + $data);

        return response()->json($employee, 201);
    }


    public function show(Request $request, Company $company, Employee $employee)
    {
        $this->authorizeCompany($request->user(), $company);
        $this->authorizeEmployeeBelongsToCompany($employee, $company);

        return response()->json($employee);
    }

    public function update(Request $request, Company $company, Employee $employee)
    {
        $this->authorizeCompany($request->user(), $company);

        $data = $request->validate([
            'employee_code'  => 'sometimes|nullable|string|max:50',
            'name'           => 'sometimes|required|string|max:255',
            'tc_no'          => 'sometimes|nullable|string|min:11|max:11',
            'position'       => 'sometimes|nullable|string|max:100',
            'department'     => 'sometimes|nullable|string|max:100',
            'start_date'     => 'sometimes|nullable|date',
            'status'         => 'sometimes|nullable|string|in:active,inactive',
            'wallet_address' => [
                'sometimes',
                'nullable',
                'string',
                'max:42',
                'regex:/^0x[0-9a-fA-F]{40}$/',
            ],
        ], [
            'tc_no.min' => 'TC Kimlik No 11 haneli olmalıdır.',
            'tc_no.max' => 'TC Kimlik No 11 haneli olmalıdır.',
            'wallet_address.regex' => 'Wallet adresi geçerli bir Ethereum adresi olmalıdır.',
        ]);

        $employee->update($data);

        return response()->json($employee);
    }



    public function destroy(Request $request, Company $company, Employee $employee)
    {
        $this->authorizeCompany($request->user(), $company);
        $this->authorizeEmployeeBelongsToCompany($employee, $company);

        $employee->delete();

        return response()->json(['message' => 'Silindi']);
    }

    protected function authorizeCompany($user, Company $company)
    {
        if ($company->owner_id !== $user->id) {
            abort(403, 'Bu şirkete erişim yetkiniz yok');
        }
    }

    protected function authorizeEmployeeBelongsToCompany(Employee $employee, Company $company)
    {
        if ($employee->company_id !== $company->id) {
            abort(404, 'Çalışan bu şirkete ait değil');
        }
    }

    public function nfts(Request $request, Company $company, Employee $employee)
    {
        // Şirket sahibi mi?
        if ($company->owner_id !== $request->user()->id) {
            abort(403, 'Yetkisiz');
        }

        // Çalışan gerçekten bu şirkete mi ait?
        if ($employee->company_id !== $company->id) {
            abort(404, 'Çalışan bu şirkete ait değil');
        }

        // Bu çalışanın tüm payroll'larına bağlı NFT kayıtlarını çek
        $nfts = NftMint::whereHas('payroll', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
            ->with('payroll')
            ->orderByDesc('id')
            ->get()
            ->map(function (NftMint $nft) {
                return [
                    'id'           => $nft->id,
                    'status'       => $nft->status,
                    'token_id'     => $nft->token_id,
                    'tx_hash'      => $nft->tx_hash,
                    'ipfs_cid'     => $nft->ipfs_cid,
                    'ipfs_url'     => $nft->ipfs_cid
                        ? 'https://ipfs.io/ipfs/'.$nft->ipfs_cid
                        : null,
                    'explorer_url' => $nft->tx_hash
                        ? 'https://sepolia.etherscan.io/tx/'.$nft->tx_hash
                        : null,

                    'payroll'      => $nft->payroll ? [
                        'id'           => $nft->payroll->id,
                        'period_start' => $nft->payroll->period_start,
                        'period_end'   => $nft->payroll->period_end,
                        'gross_salary' => $nft->payroll->gross_salary,
                        'net_salary'   => $nft->payroll->net_salary,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $nfts,
        ]);
    }
}
