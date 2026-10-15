<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateInitialAdmin extends Command
{
    protected $signature = 'risengrowth:create-admin';
    protected $description = 'Create the first administrator, only when no admin exists';

    public function handle(): int
    {
        if (User::query()->where('role', 'admin')->exists()) {
            $this->error('Admin sudah ada. Perintah bootstrap hanya dapat digunakan untuk admin pertama.');
            return self::FAILURE;
        }
        $name = $this->ask('Nama admin');
        $email = $this->ask('Email admin');
        $password = $this->secret('Password admin (minimal 12 karakter, huruf besar/kecil, angka)');
        $confirmation = $this->secret('Ulangi password admin');
        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ] + []);
        if ($password !== $confirmation || $validator->fails()) {
            $this->error('Validasi gagal: periksa nama, email unik, password dan konfirmasi.');
            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => $password,
            'role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $this->info('Admin pertama berhasil dibuat.');
        return self::SUCCESS;
    }
}
