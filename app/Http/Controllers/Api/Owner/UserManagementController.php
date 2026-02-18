<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Concerns\AuthorizesCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    use AuthorizesCompany;

    public function index(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $users = User::where('company_id', $company->id)
            ->whereIn('role', [User::ROLE_COMPANY_MANAGER, User::ROLE_EMPLOYEE])
            ->get();

        return response()->json($users);
    }

    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($request->user(), $company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:'.User::ROLE_COMPANY_MANAGER.','.User::ROLE_EMPLOYEE],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'company_id' => $company->id,
        ]);

        return response()->json($user, 201);
    }
}
