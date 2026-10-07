<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Support\RetailId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->when($request->filled('q'), fn ($query) => $query->where('nama_barang', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('id_kategori', $request->string('category')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->where('stok', '<=', 5))
            ->orderBy('nama_barang');

        if ($request->user()->role !== 'KASIR') {
            $query->with([
                'category:id_kategori,nama_kategori',
                'distributor:id_distributor,nama_distributor',
            ]);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_kategori' => ['required', 'exists:tb_kategori,id_kategori'],
            'id_distributor' => ['required', 'exists:tb_distributor,id_distributor'],
            'nama_barang' => ['required', 'string', 'max:50'],
            'harga_satuan' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'stok' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ]);

        $product = Product::create(['id_barang' => RetailId::generate('tb_barang', 'id_barang', 'BRG'), ...$data]);

        return response()->json(['data' => $product->load(['category', 'distributor'])], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $product->update($request->validate([
            'id_kategori' => ['required', 'exists:tb_kategori,id_kategori'],
            'id_distributor' => ['required', 'exists:tb_distributor,id_distributor'],
            'nama_barang' => ['required', 'string', 'max:50'],
            'harga_satuan' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'stok' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ]));

        return response()->json(['data' => $product->fresh()->load(['category', 'distributor'])]);
    }

    public function deleteProduct(Product $product): JsonResponse
    {
        if ($product->sales()->exists()) {
            throw ValidationException::withMessages([
                'product' => 'Barang yang sudah tercatat pada penjualan tidak dapat dihapus.',
            ]);
        }

        $product->delete();

        return response()->json(['message' => 'Barang berhasil dihapus.']);
    }

    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'operasi' => ['required', 'in:tambah,kurangi'],
        ]);

        $updated = DB::transaction(function () use ($data, $product): Product {
            $locked = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $delta = $data['operasi'] === 'tambah' ? $data['jumlah'] : -$data['jumlah'];

            if ($locked->stok + $delta < 0 || $locked->stok + $delta > 2147483647) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Penyesuaian stok berada di luar batas yang diizinkan.',
                ]);
            }

            $locked->increment('stok', $delta);
            InventoryLog::create([
                'nama_operasi' => $delta > 0 ? 'TAMBAH STOK' : 'KOREKSI STOK',
                'rincian' => "Barang {$locked->id_barang} ".($delta > 0 ? 'bertambah' : 'berkurang').' sebanyak '.abs($delta),
                'waktu_eksekusi' => now(),
            ]);

            return $locked->refresh();
        });

        return response()->json(['data' => $updated]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::orderBy('nama_kategori')->get()]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['nama_kategori' => ['required', 'string', 'max:30', 'unique:tb_kategori,nama_kategori']]);
        $category = Category::create(['id_kategori' => RetailId::generate('tb_kategori', 'id_kategori', 'KAT'), ...$data]);

        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $category->update($request->validate([
            'nama_kategori' => ['required', 'string', 'max:30', 'unique:tb_kategori,nama_kategori,'.$category->id_kategori.',id_kategori'],
        ]));

        return response()->json(['data' => $category->fresh()]);
    }

    public function deleteCategory(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages(['category' => 'Kategori masih digunakan oleh barang.']);
        }

        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }

    public function distributors(): JsonResponse
    {
        return response()->json(['data' => Distributor::orderBy('nama_distributor')->get()]);
    }

    public function storeDistributor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_distributor' => ['required', 'string', 'max:50'],
            'kota_asal' => ['required', 'string', 'max:30'],
            'kontak' => ['required', 'string', 'max:15'],
        ]);
        $distributor = Distributor::create(['id_distributor' => RetailId::generate('tb_distributor', 'id_distributor', 'DST'), ...$data]);

        return response()->json(['data' => $distributor], 201);
    }

    public function updateDistributor(Request $request, Distributor $distributor): JsonResponse
    {
        $distributor->update($request->validate([
            'nama_distributor' => ['required', 'string', 'max:50'],
            'kota_asal' => ['required', 'string', 'max:30'],
            'kontak' => ['required', 'string', 'max:15'],
        ]));

        return response()->json(['data' => $distributor->fresh()]);
    }

    public function deleteDistributor(Distributor $distributor): JsonResponse
    {
        if ($distributor->products()->exists()) {
            throw ValidationException::withMessages(['distributor' => 'Distributor masih memiliki barang.']);
        }

        $distributor->delete();

        return response()->json(['message' => 'Distributor berhasil dihapus.']);
    }
}
