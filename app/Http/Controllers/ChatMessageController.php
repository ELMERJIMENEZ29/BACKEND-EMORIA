<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatMessage;

class ChatMessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // Obtener historial reciente para dar contexto al bot
        $history = ChatMessage::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse()
            ->flatMap(fn($msg) => [
                ['role' => 'user',  'parts' => [['text' => $msg->user_message]]],
                ['role' => 'model', 'parts' => [['text' => $msg->bot_response]]],
            ])
            ->values()
            ->toArray();

        // Agregar el mensaje actual
        $history[] = [
            'role'  => 'user',
            'parts' => [['text' => $request->message]],
        ];

        // Llamada a Gemini
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . env('GEMINI_API_KEY'), [
            'system_instruction' => [
                'parts' => [['text' => 'Eres un asistente de apoyo emocional empático y comprensivo. Responde siempre en español de forma cálida y breve.']]
            ],
            'contents' => $history,
        ]);

        $botResponse = $response->json('candidates.0.content.parts.0.text') ?? 'No pude procesar tu mensaje.';

        // Guardar en BD
        $chat = ChatMessage::create([
            'user_id'      => $request->user()->id,
            'user_message' => $request->message,
            'bot_response' => $botResponse,
        ]);

        return response()->json($chat, 201);
    }

    public function index(Request $request)
    {
        $messages = ChatMessage::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json($messages);
    }
}
