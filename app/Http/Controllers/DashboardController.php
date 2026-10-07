<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $productQuery = Product::query();

        $summary = [
            'products' => (clone $productQuery)->count(),
            'stock_units' => (clone $productQuery)->sum('stok'),
            'inventory_value' => (int) (clone $productQuery)->selectRaw('COALESCE(SUM(stok * harga_satuan), 0) as value')->value('value'),
            'low_stock' => (clone $productQuery)->where('stok', '<=', 5)->count(),
            'out_of_stock' => (clone $productQuery)->where('stok', 0)->count(),
        ];

        $inventoryOnly = $request->user()->role === 'ADMIN_BARANG';
        $salesSummary = null;
        $recentSales = [];
        $salesChart = [];
        $lowStockQuery = Product::where('stok', '<=', 5)->orderBy('stok')->limit(5);

        if ($request->user()->role !== 'KASIR') {
            $lowStockQuery->with(['category:id_kategori,nama_kategori']);
        }

        if (! $inventoryOnly) {
            $salesSummary = [
                'today_count' => Sale::whereDate('tanggal_jual', $today)->count(),
                'today_revenue' => (int) Sale::whereDate('tanggal_jual', $today)->sum('total_bayar'),
                'month_revenue' => (int) Sale::whereBetween('tanggal_jual', [$today->copy()->startOfMonth(), $today])->sum('total_bayar'),
                'all_time_revenue' => (int) Sale::sum('total_bayar'),
            ];

            $recentSales = Sale::with(['customer:id_pelanggan,nama_pelanggan', 'product:id_barang,nama_barang'])
                ->orderByDesc('tanggal_jual')
                ->orderByDesc('id_penjualan')
                ->limit(6)
                ->get();

            $start = $today->copy()->subMonths(5)->startOfMonth();
            $monthlyTotals = Sale::whereBetween('tanggal_jual', [$start, $today])
                ->get(['tanggal_jual', 'total_bayar'])
                ->groupBy(fn (Sale $sale): string => $sale->tanggal_jual->format('Y-m'))
                ->map(fn ($sales): int => (int) $sales->sum('total_bayar'));

            for ($month = $start->copy(); $month <= $today; $month->addMonth()) {
                $salesChart[] = [
                    'label' => $month->translatedFormat('M'),
                    'month' => $month->format('Y-m'),
                    'revenue' => $monthlyTotals->get($month->format('Y-m'), 0),
                ];
            }
        }

        return response()->json([
            'summary' => $summary,
            'sales' => $salesSummary,
            'recent_sales' => $recentSales,
            'sales_chart' => $salesChart,
            'low_stock_products' => $lowStockQuery->get(),
        ]);
    }
}
