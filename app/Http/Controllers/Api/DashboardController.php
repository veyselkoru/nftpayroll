<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        // 1) Kullanıcının sahip olduğu şirketler
        $companyIds = Company::where('owner_id', $user->id)->pluck('id');

        // 2) Bu şirketlere bağlı çalışanlar
        $employeeIds = Employee::whereIn('company_id', $companyIds)->pluck('id');

        // 3) Payroll ve NFT sayıları
        $totalCompanies  = $companyIds->count();
        $totalEmployees  = $employeeIds->count();
        $totalPayrolls   = Payroll::whereIn('employee_id', $employeeIds)->count();

        $nftQuery = NftMint::whereHas('payroll.employee', function ($q) use ($companyIds) {
            $q->whereIn('company_id', $companyIds);
        });

        $totalNfts = $nftQuery->count();

        $nftsByStatus = $nftQuery
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status'); // ["pending" => 3, "sent" => 5, ...]

        return response()->json([
            'companies' => $totalCompanies,
            'employees' => $totalEmployees,
            'payrolls'  => $totalPayrolls,
            'nfts'      => [
                'total'     => $totalNfts,
                'by_status' => $nftsByStatus,
            ],
        ]);
    }

    public function recentMints(Request $request)
    {
        $user = $request->user();

        // 1) Kullanıcının sahip olduğu şirketler
        $companyIds = Company::where('owner_id', $user->id)->pluck('id');

        if ($companyIds->isEmpty()) {
            return response()->json([
                'items' => [],
            ]);
        }

        // 2) Bu şirketlere bağlı çalışanlar
        $employeeIds = Employee::whereIn('company_id', $companyIds)->pluck('id');

        if ($employeeIds->isEmpty()) {
            return response()->json([
                'items' => [],
            ]);
        }

        // 3) NftMint kayıtlarını getir (son 5)
        $mints = NftMint::whereHas('payroll', function ($q) use ($employeeIds) {
                $q->whereIn('employee_id', $employeeIds);
            })
            ->with(['payroll.employee'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (NftMint $mint) {
                $employee = $mint->payroll?->employee;

                return [
                    'id'          => $mint->id,
                    'payroll_id'  => $mint->payroll_id,
                    'employee'    => $employee?->name,
                    'employee_id' => $employee?->id,
                    'company_id'  => $employee?->company_id,
                    'status'      => $mint->status,
                    'tx_hash'     => $mint->tx_hash,
                    'ipfs_cid'    => $mint->ipfs_cid,
                    'created_at'  => $mint->created_at,
                ];
            });

        return response()->json([
            'items' => $mints,
        ]);
    }

}
