<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $resetUrl = null;

        $status = Password::sendResetLink($validated, function ($user, string $token) use (&$resetUrl) {
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $resetUrl = $frontendUrl . '/?reset_token=' . urlencode($token) . '&email=' . urlencode($user->email);

            Mail::raw(
                "Hola {$user->username},\n\nRecibimos una solicitud para restablecer tu contrasena de EMORIA.\n\nAbre este enlace para crear una nueva contrasena:\n{$resetUrl}\n\nEste enlace expira en 60 minutos. Si no solicitaste el cambio, puedes ignorar este mensaje.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Restablece tu contrasena de EMORIA');
                }
            );
        });

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Ya enviamos un enlace hace poco. Revisa tu correo o intenta nuevamente en un minuto.',
            ], 429);
        }

        $response = [
            'message' => 'Si el correo existe en EMORIA, enviaremos un enlace para restablecer la contrasena.',
        ];

        if (app()->environment('local') && config('mail.default') === 'log' && $resetUrl) {
            $response['dev_reset_url'] = $resetUrl;
        }

        return response()->json($response);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $validated,
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'El enlace de restablecimiento no es valido o ya expiro.',
            ], 422);
        }

        return response()->json([
            'message' => 'Contrasena actualizada. Ya puedes iniciar sesion.',
        ]);
    }
}
