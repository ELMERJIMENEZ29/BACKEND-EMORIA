<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users|max:50',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'USUARIO',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request, TotpService $totp)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'two_factor_code' => 'nullable|string',
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            if (!$request->filled('two_factor_code')) {
                return response()->json([
                    'message' => 'Se requiere codigo 2FA',
                    'two_factor_required' => true,
                ], 428);
            }

            if (!$totp->verify(decrypt($user->two_factor_secret), $request->two_factor_code)) {
                return response()->json([
                    'message' => 'Codigo 2FA invalido',
                    'two_factor_required' => true,
                ], 422);
            }
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada']);
    }
}
