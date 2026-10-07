<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'tb_pelanggan';

    protected $primaryKey = 'id_pelanggan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_pelanggan',
        'nama_pelanggan',
        'jenis_kelamin',
        'alamat',
        'tanggal_daftar',
    ];

    protected function casts(): array
    {
        return ['tanggal_daftar' => 'date'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'id_pelanggan', 'id_pelanggan');
    }
}
