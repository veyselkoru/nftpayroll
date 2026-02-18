<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => User::ROLE_COMPANY_OWNER,
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $this->buildAuthUserPayload($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Bu kimlik bilgileri hatalı.'],
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $this->buildAuthUserPayload($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json($this->buildAuthUserPayload($user));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı']);
    }

    protected function buildAuthUserPayload(User $user): array
    {
        $role = $user->normalizedRole();

        $apiPrefix = match ($role) {
            User::ROLE_COMPANY_OWNER => '/api/owner',
            User::ROLE_COMPANY_MANAGER => '/api/manager',
            User::ROLE_EMPLOYEE => '/api/employee',
            default => '/api',
        };

        return array_merge($user->toArray(), [
            'role' => $role,
            'api_prefix' => $apiPrefix,
        ]);
    }
}
