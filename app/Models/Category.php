<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'tb_kategori';

    protected $primaryKey = 'id_kategori';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id_kategori', 'nama_kategori'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'id_kategori', 'id_kategori');
    }
}
