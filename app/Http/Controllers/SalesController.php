<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Support\RetailId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $sales = Sale::with([
            'customer:id_pelanggan,nama_pelanggan',
            'product:id_barang,nama_barang,harga_satuan',
        ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('id_penjualan', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('nama_pelanggan', 'like', $term))
                        ->orWhereHas('product', fn ($product) => $product->where('nama_barang', 'like', $term));
                });
            })
            ->when($period['from'] ?? null, fn ($query, $from) => $query->whereDate('tanggal_jual', '>=', $from))
            ->when($period['to'] ?? null, fn ($query, $to) => $query->whereDate('tanggal_jual', '<=', $to))
            ->orderByDesc('tanggal_jual')
            ->orderByDesc('id_penjualan')
            ->limit(200)
            ->get();

        return response()->json(['data' => $sales]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_pelanggan' => ['required', 'exists:tb_pelanggan,id_pelanggan'],
            'id_barang' => ['required', 'exists:tb_barang,id_barang'],
            'jumlah_beli' => ['required', 'integer', 'min:1', 'max:99999'],
        ]);

        $sale = DB::transaction(function () use ($data): Sale {
            $product = Product::whereKey($data['id_barang'])->lockForUpdate()->firstOrFail();

            if ($product->stok < $data['jumlah_beli']) {
                throw ValidationException::withMessages([
                    'jumlah_beli' => "Stok {$product->nama_barang} tersisa {$product->stok} unit.",
                ]);
            }

            $mysqlTriggersHandleInventory = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
            $subtotal = $product->harga_satuan * $data['jumlah_beli'];
            if ($subtotal > 2147483647) {
                throw ValidationException::withMessages([
                    'jumlah_beli' => 'Total transaksi melebihi kapasitas kolom total_bayar.',
                ]);
            }
            $total = $mysqlTriggersHandleInventory || $data['jumlah_beli'] < 50
                ? $subtotal
                : (int) floor($subtotal * 0.9);

            $sale = Sale::create([
                'id_penjualan' => RetailId::generate('tb_penjualan', 'id_penjualan', 'TRX'),
                'id_pelanggan' => $data['id_pelanggan'],
                'id_barang' => $product->id_barang,
                'tanggal_jual' => now()->toDateString(),
                'jumlah_beli' => $data['jumlah_beli'],
                'total_bayar' => $total,
                'status_transaksi' => 'LUNAS',
            ]);

            if (! $mysqlTriggersHandleInventory) {
                $product->decrement('stok', $data['jumlah_beli']);
                InventoryLog::create([
                    'nama_operasi' => 'POTONG STOK',
                    'rincian' => "Barang {$product->id_barang} berkurang sebanyak {$data['jumlah_beli']}",
                    'waktu_eksekusi' => now(),
                ]);
            }

            return $sale;
        });

        return response()->json([
            'data' => $sale->fresh()->load([
                'customer:id_pelanggan,nama_pelanggan',
                'product:id_barang,nama_barang,harga_satuan',
            ]),
        ], 201);
    }

    public function destroy(Sale $sale): JsonResponse
    {
        DB::transaction(function () use ($sale): void {
            $lockedSale = Sale::whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $mysqlTriggersHandleInventory = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

            if (! $mysqlTriggersHandleInventory) {
                $product = Product::whereKey($lockedSale->id_barang)->lockForUpdate()->firstOrFail();
                $product->increment('stok', $lockedSale->jumlah_beli);
                InventoryLog::create([
                    'nama_operasi' => 'PEMULIHAN STOK',
                    'rincian' => "Barang {$product->id_barang} dikembalikan sebanyak {$lockedSale->jumlah_beli}",
                    'waktu_eksekusi' => now(),
                ]);
            }

            $lockedSale->delete();
        });

        return response()->json(['message' => 'Transaksi dibatalkan dan stok dipulihkan.']);
    }
}
