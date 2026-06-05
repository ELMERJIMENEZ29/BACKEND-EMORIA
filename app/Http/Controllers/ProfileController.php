<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'gender' => 'nullable|in:male,female,other',
            'universe' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|max:4096',
            'avatar_url' => 'nullable|url',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $path = $file->store('avatars', 'public');
            $user->avatar = $path;
        } elseif ($request->filled('avatar_url')) {
            $user->avatar = $request->input('avatar_url');
        }

        if ($request->filled('gender')) {
            $user->gender = $request->input('gender');
        }

        if ($request->filled('universe')) {
            $user->universe = $request->input('universe');
        }

        $user->save();

        return response()->json([
            'user' => $user,
            'avatar_url' => $user->avatar_url ?? null,
        ]);
    }
}
