<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $table = 'tb_log_inventori';

    protected $primaryKey = 'id_log';

    public $timestamps = false;

    protected $fillable = ['nama_operasi', 'rincian', 'waktu_eksekusi'];

    protected function casts(): array
    {
        return ['waktu_eksekusi' => 'datetime'];
    }
}
