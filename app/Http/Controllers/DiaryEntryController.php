<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiaryEntry;

class DiaryEntryController extends Controller
{
    // Listar todas las entradas del usuario autenticado
    public function index(Request $request)
    {
        $entries = DiaryEntry::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entries);
    }

    // Crear una nueva entrada
    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $entry = DiaryEntry::create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
        ]);

        return response()->json($entry, 201);
    }

    // Ver una entrada específica
    public function show(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json($entry);
    }

    // Eliminar una entrada
    public function destroy(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $entry->delete();

        return response()->json(['message' => 'Entrada eliminada']);
    }
}
