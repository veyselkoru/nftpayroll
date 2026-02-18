<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesCompany;
use App\Mail\CompanyOwnerWelcomeMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    use AuthorizesCompany {
        authorizeCompany as protected authorizeCompanyByRole;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->normalizedRole();

        $companies = match ($role) {
            \App\Models\User::ROLE_COMPANY_OWNER => Company::where('owner_id', $user->id)->get(),
            \App\Models\User::ROLE_COMPANY_MANAGER,
            \App\Models\User::ROLE_EMPLOYEE => Company::where('id', $user->company_id)->get(),
            default => collect(),
        };

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
            'owner_name'         => 'nullable|string|max:255',
            'owner_email'        => 'nullable|email|max:255|unique:users,email',
        ]);

        $ownerEmail = $data['owner_email'] ?? $data['contact_email'] ?? null;
        if (! $ownerEmail) {
            throw ValidationException::withMessages([
                'owner_email' => ['owner_email veya contact_email zorunludur.'],
            ]);
        }
        if (User::where('email', $ownerEmail)->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => ['Bu e-posta ile kullanici zaten mevcut.'],
            ]);
        }

        $ownerName = $data['owner_name'] ?? ($data['name'].' Owner');
        $plainPassword = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $companyPayload = collect($data)->except(['owner_name', 'owner_email'])->toArray();

        [$ownerUser, $company] = DB::transaction(function () use ($ownerName, $ownerEmail, $plainPassword, $companyPayload) {
            $ownerUser = User::create([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => Hash::make($plainPassword),
                'role' => User::ROLE_COMPANY_OWNER,
            ]);

            $company = Company::create([
                'owner_id' => $ownerUser->id,
            ] + $companyPayload);

            $ownerUser->update(['company_id' => $company->id]);

            return [$ownerUser, $company];
        });

        if (empty($request->user()->company_id)) {
            $request->user()->update(['company_id' => $company->id]);
        }

        Mail::to($ownerUser->email)->queue(new CompanyOwnerWelcomeMail(
            ownerName: $ownerUser->name,
            companyName: $company->name,
            email: $ownerUser->email,
            plainPassword: $plainPassword,
        ));

        return response()->json([
            'company' => $company,
            'owner_user_id' => $ownerUser->id,
            'credentials_sent' => true,
        ], 201);
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
        $this->authorizeCompanyByRole($user, $company);
    }


    public function nfts(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

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
