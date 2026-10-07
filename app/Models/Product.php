<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'tb_barang';

    protected $primaryKey = 'id_barang';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_barang',
        'id_kategori',
        'id_distributor',
        'nama_barang',
        'harga_satuan',
        'stok',
    ];

    protected function casts(): array
    {
        return ['harga_satuan' => 'integer', 'stok' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'id_kategori', 'id_kategori');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'id_distributor', 'id_distributor');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'id_barang', 'id_barang');
    }
}
