<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\RetailId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->filled('q'), fn ($query) => $query->where('nama_pelanggan', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderBy('nama_pelanggan')
            ->get();

        return response()->json(['data' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::create([
            'id_pelanggan' => RetailId::generate('tb_pelanggan', 'id_pelanggan', 'PLG'),
            ...$this->validated($request),
            'tanggal_daftar' => now()->toDateString(),
        ]);

        return response()->json(['data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request));

        return response()->json(['data' => $customer->fresh()]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->sales()->exists()) {
            throw ValidationException::withMessages(['customer' => 'Pelanggan yang telah bertransaksi tidak dapat dihapus.']);
        }

        $customer->delete();

        return response()->json(['message' => 'Pelanggan berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_pelanggan' => ['required', 'string', 'max:50'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['required', 'string', 'max:100'],
        ]);
    }
}
