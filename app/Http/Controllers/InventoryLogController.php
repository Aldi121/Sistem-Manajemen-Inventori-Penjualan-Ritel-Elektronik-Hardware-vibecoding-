<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = InventoryLog::query()
            ->when($request->filled('q'), fn ($query) => $query->where('rincian', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderByDesc('waktu_eksekusi')
            ->limit(200)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
