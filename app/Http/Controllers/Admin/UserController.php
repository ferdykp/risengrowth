<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['admin', 'member'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $users = User::query()->when($filters['search'] ?? null, fn ($q, $value) =>
            $q->where(fn ($sub) => $sub->where('name', 'like', '%'.addcslashes($value, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($value, '%_\\').'%')))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')->paginate(12)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function create(): View { return view('admin.users.form', ['user' => new User()]); }
    public function edit(User $user): View { return view('admin.users.form', compact('user')); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'member'])],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        User::create([...$data, 'is_active' => true, 'must_change_password' => true]);
        return redirect()->route('admin.users.index')->with('success', 'Akun dibuat. Bagikan email dan password sementara melalui saluran aman.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);
        DB::transaction(function () use ($request, $user, $data) {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($target->id === $request->user()->id && $data['role'] !== 'admin') {
                throw ValidationException::withMessages(['role' => 'Tidak bisa menurunkan role akun sendiri.']);
            }
            if ($target->role === 'admin' && $target->is_active && $data['role'] !== 'admin') {
                $this->guardLastAdmin($target);
            }
            $target->update($data);
        });
        return redirect()->route('admin.users.index')->with('success', 'Data pengguna diperbarui.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($target->id === $request->user()->id) {
                throw ValidationException::withMessages(['account' => 'Tidak bisa menonaktifkan akun sendiri.']);
            }
            if ($target->role === 'admin' && $target->is_active) $this->guardLastAdmin($target);
            $target->forceFill([
                'is_active' => ! $target->is_active,
                'remember_token' => Str::random(60),
            ])->save();
        });
        return back()->with('success', 'Status pengguna diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();
        return redirect()->route('admin.users.index')->with('success', 'Password sementara disetel ulang. Berikan langsung kepada pengguna melalui saluran aman.');
    }

    private function guardLastAdmin(User $target): void
    {
        if (User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'Admin aktif terakhir tidak boleh dinonaktifkan atau diturunkan.']);
        }
    }
}
