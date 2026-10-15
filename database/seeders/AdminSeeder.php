<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name = env('ADMIN_NAME', 'Administrator');

        if (blank($email) || blank($password)) {
            throw new RuntimeException(
                'ADMIN_EMAIL dan ADMIN_PASSWORD wajib diisi di .env.'
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'Password admin minimal 12 karakter.'
            );
        }

        User::query()->updateOrCreate(
            [
                'email' => Str::lower(trim($email)),
            ],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info(
            'Akun administrator RisenGrowth berhasil disiapkan.'
        );
    }
}
