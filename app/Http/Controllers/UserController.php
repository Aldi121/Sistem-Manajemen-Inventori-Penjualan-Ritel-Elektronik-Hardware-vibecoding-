<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RetailId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => User::query()->orderBy('nama_lengkap')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30', 'unique:tb_pengguna,username'],
            'nama_lengkap' => ['required', 'string', 'max:50'],
            'role' => ['required', Rule::in(['ADMIN', 'ADMIN_BARANG', 'KASIR'])],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $user = User::create([
            'id_user' => RetailId::generate('tb_pengguna', 'id_user', 'USR'),
            'username' => $data['username'],
            'nama_lengkap' => $data['nama_lengkap'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json(['data' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30', Rule::unique('tb_pengguna', 'username')->ignore($user->id_user, 'id_user')],
            'nama_lengkap' => ['required', 'string', 'max:50'],
            'role' => ['required', Rule::in(['ADMIN', 'ADMIN_BARANG', 'KASIR'])],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        if ($user->role === 'ADMIN' && $data['role'] !== 'ADMIN' && User::where('role', 'ADMIN')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'Aplikasi harus memiliki setidaknya satu administrator.']);
        }

        $user->username = $data['username'];
        $user->nama_lengkap = $data['nama_lengkap'];
        $user->role = $data['role'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json(['data' => $user]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        if ($user->role === 'ADMIN' && User::where('role', 'ADMIN')->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Aplikasi harus memiliki setidaknya satu administrator.']);
        }

        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
