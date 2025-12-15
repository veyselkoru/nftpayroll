<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesCompany;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use AuthorizesCompany;

    public function index(Request $request)
    {
        // Sadece kullanıcının sahip olduğu şirketler
        $companies = Company::where('owner_id', $request->user()->id)->get();

        return response()->json($companies);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'type'               => 'nullable|string|max:50',
            'tax_number'         => 'nullable|string|max:50',
            'registration_number'=> 'nullable|string|max:50',
            'country'            => 'nullable|string|max:100',
            'city'               => 'nullable|string|max:100',
            'address'            => 'nullable|string|max:255',
            'contact_phone'      => 'nullable|string|max:50',
            'contact_email'      => 'nullable|email|max:255',
        ]);

        $company = Company::create([
            'owner_id' => $request->user()->id,
        ] + $data);

        return response()->json($company, 201);
    }

    public function update(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $data = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'type'               => 'nullable|string|max:50',
            'tax_number'         => 'nullable|string|max:50',
            'registration_number'=> 'nullable|string|max:50',
            'country'            => 'nullable|string|max:100',
            'city'               => 'nullable|string|max:100',
            'address'            => 'nullable|string|max:255',
            'contact_phone'      => 'nullable|string|max:50',
            'contact_email'      => 'nullable|email|max:255',
        ]);

        $company->update($data);

        return response()->json($company);
    }


    public function show(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

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


    public function nfts(Request $request, Company $company)
    {
        // Şirket sahibi mi? (diğer action’lardaki authorizeCompany ile uyumlu)
        if ($company->owner_id !== $request->user()->id) {
            abort(403, 'Bu şirkete erişim yetkiniz yok');
        }

        $status = $request->query('status');      // pending | sending | sent | failed | (boş = hepsi)
        $search = $request->query('search');      // çalışan adı, tx hash, token id için
        $perPage = (int) $request->query('per_page', 100); // default 100, istersen değiştir

        $query = \App\Models\NftMint::whereHas('payroll.employee', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->with(['payroll.employee']);

        // Status filtresi (opsiyonel)
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Arama filtresi (opsiyonel)
        if ($search) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('payroll.employee', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
                })
                ->orWhere('tx_hash', 'like', "%{$search}%")
                ->orWhere('token_id', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        // Collection’ı map’leyip insan okunur hale getiriyoruz
        $nfts = $paginator->getCollection()->map(function ($mint) {
            return [
                'id'           => $mint->id,
                'token_id'     => $mint->token_id,
                'tx_hash'      => $mint->tx_hash,
                'employee'     => $mint->payroll->employee->name ?? null,
                'national_id'  => $mint->payroll->employee->national_id ?? null,
                'ipfs_url'     => $mint->ipfs_cid
                    ? 'https://ipfs.io/ipfs/'.$mint->ipfs_cid
                    : null,
                'explorer_url' => $mint->tx_hash
                    ? 'https://sepolia.etherscan.io/tx/'.$mint->tx_hash
                    : null,
                'image_url'    => $mint->image_url, // accessor’dan geliyor
                'status'       => $mint->status,
                'created_at'   => $mint->created_at,
                'created_at_formatted' => optional($mint->created_at)->format('Y-m-d H:i'),
            ];
        })->values();

        return response()->json([
            'company_id'   => $company->id,
            'nfts'         => $nfts,
            // pagination meta – mevcut frontend sadece nfts’i kullanmaya devam edebilir
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }


}
