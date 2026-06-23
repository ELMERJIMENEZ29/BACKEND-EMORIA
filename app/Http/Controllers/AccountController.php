<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'La contraseña no es correcta'], 422);
        }

        $user->tokens()->delete();
        ActivityLog::where('user_id', $user->id)->delete();
        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada']);
    }
}
