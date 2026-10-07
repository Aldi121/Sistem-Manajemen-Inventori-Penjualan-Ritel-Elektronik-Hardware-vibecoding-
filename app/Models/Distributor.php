<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distributor extends Model
{
    protected $table = 'tb_distributor';

    protected $primaryKey = 'id_distributor';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id_distributor', 'nama_distributor', 'kota_asal', 'kontak'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'id_distributor', 'id_distributor');
    }
}
