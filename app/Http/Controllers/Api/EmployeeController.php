<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
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
            'name'          => 'required|string|max:255',
            'surname'       => 'required|string|max:255',
            'email'         => 'nullable|email',
            'wallet_address'=> 'nullable|string|max:255',
            'national_id'   => 'nullable|string|max:50',
        ]);

        $employee = $company->employees()->create($data);

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
        $this->authorizeEmployeeBelongsToCompany($employee, $company);

        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'surname'       => 'sometimes|required|string|max:255',
            'email'         => 'nullable|email',
            'wallet_address'=> 'nullable|string|max:255',
            'national_id'   => 'nullable|string|max:50',
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
