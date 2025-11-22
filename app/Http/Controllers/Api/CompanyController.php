<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        // Sadece kullanıcının sahip olduğu şirketler
        $companies = Company::where('owner_id', $request->user()->id)->get();

        return response()->json($companies);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'tax_number' => 'nullable|string|max:50',
            'country'    => 'nullable|string|max:100',
            'city'       => 'nullable|string|max:100',
        ]);

        $company = Company::create([
            'owner_id'   => $request->user()->id,
            'name'       => $data['name'],
            'tax_number' => $data['tax_number'] ?? null,
            'country'    => $data['country'] ?? null,
            'city'       => $data['city'] ?? null,
        ]);

        return response()->json($company, 201);
    }

    public function show(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        return response()->json($company);
    }

    public function update(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'tax_number' => 'nullable|string|max:50',
            'country'    => 'nullable|string|max:100',
            'city'       => 'nullable|string|max:100',
        ]);

        $company->update($data);

        return response()->json($company);
    }

    public function destroy(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $company->delete();

        return response()->json(['message' => 'Silindi']);
    }

    protected function authorizeCompany($user, Company $company)
    {
        if ($company->owner_id !== $user->id) {
            abort(403, 'Bu şirkete erişim yetkiniz yok');
        }
    }


    public function nfts(Company $company)
    {
        $nfts = \App\Models\NftMint::whereHas('payroll', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with('payroll.employee')
        ->orderBy('id', 'desc')
        ->get()
        ->map(function ($mint) {
            return [
                'id'           => $mint->id,
                'token_id'     => $mint->token_id,
                'tx_hash'      => $mint->tx_hash,
                'employee'     => $mint->payroll->employee->name ?? null,
                'ipfs_url'     => 'https://ipfs.io/ipfs/'.$mint->ipfs_cid,
                'explorer_url' => $mint->tx_hash
                    ? 'https://sepolia.etherscan.io/tx/'.$mint->tx_hash
                    : null,
                'status'      => $mint->status,
                'created_at'  => $mint->created_at,
            ];
        });

        return response()->json([
            'company_id' => $company->id,
            'nfts'       => $nfts,
        ]);
    }

}
