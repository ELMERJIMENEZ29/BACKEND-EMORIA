<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_INITIAL_EMAIL', 'administracion@emoria.com')],
            [
                'username' => env('ADMIN_INITIAL_USERNAME', 'admin'),
                'password' => Hash::make(env('ADMIN_INITIAL_PASSWORD', 'EmoriaAdmin123')),
                'role' => 'ADMIN',
            ]
        );
    }
}
