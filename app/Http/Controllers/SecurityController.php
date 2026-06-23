<?php

namespace App\Http\Controllers;

use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SecurityController extends Controller
{
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta'], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Contraseña actualizada',
            'token' => $token,
        ]);
    }

    public function enableTwoFactor(Request $request, TotpService $totp)
    {
        $user = $request->user();
        $secret = $totp->generateSecret();

        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $totp->otpauthUrl(config('app.name', 'EMORIA'), $user->username, $secret),
            'enabled' => false,
            'message' => 'Escanea el secreto y confirma con un código para activar 2FA',
        ]);
    }

    public function verifyTwoFactor(Request $request, TotpService $totp)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Primero debes iniciar la activación de 2FA'], 422);
        }

        $secret = decrypt($user->two_factor_secret);

        if (!$totp->verify($secret, $validated['code'])) {
            return response()->json(['message' => 'Código 2FA inválido'], 422);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => '2FA activado',
            'enabled' => true,
        ]);
    }

    public function disableTwoFactor(Request $request, TotpService $totp)
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'code' => 'nullable|string',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'La contraseña no es correcta'], 422);
        }

        if ($user->hasTwoFactorEnabled()) {
            $secret = decrypt($user->two_factor_secret);

            if (!$request->filled('code') || !$totp->verify($secret, $validated['code'])) {
                return response()->json(['message' => 'Código 2FA inválido'], 422);
            }
        }

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'message' => '2FA desactivado',
            'enabled' => false,
        ]);
    }
}
