<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $table = 'tb_penjualan';

    protected $primaryKey = 'id_penjualan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_penjualan',
        'id_pelanggan',
        'id_barang',
        'tanggal_jual',
        'jumlah_beli',
        'total_bayar',
        'status_transaksi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_jual' => 'date',
            'jumlah_beli' => 'integer',
            'total_bayar' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'id_barang', 'id_barang');
    }
}
