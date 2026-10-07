<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $period = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Sale::with(['customer:id_pelanggan,nama_pelanggan', 'product:id_barang,nama_barang'])
            ->when($period['from'] ?? null, fn ($query, $from) => $query->whereDate('tanggal_jual', '>=', $from))
            ->when($period['to'] ?? null, fn ($query, $to) => $query->whereDate('tanggal_jual', '<=', $to));

        $sales = $query->orderByDesc('tanggal_jual')->get();

        return response()->json([
            'summary' => [
                'transactions' => $sales->count(),
                'units' => (int) $sales->sum('jumlah_beli'),
                'revenue' => (int) $sales->sum('total_bayar'),
                'average_transaction' => (int) round($sales->avg('total_bayar') ?? 0),
            ],
            'top_products' => $sales->groupBy('id_barang')
                ->map(fn ($items): array => [
                    'product' => $items->first()->product?->nama_barang ?? $items->first()->id_barang,
                    'units' => (int) $items->sum('jumlah_beli'),
                    'revenue' => (int) $items->sum('total_bayar'),
                ])
                ->sortByDesc('revenue')
                ->take(5)
                ->values(),
            'sales' => $sales,
        ]);
    }
}
