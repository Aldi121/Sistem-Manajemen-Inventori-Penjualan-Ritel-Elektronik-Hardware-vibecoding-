<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class RetailId
{
    public static function generate(string $table, string $column, string $prefix): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $id = $prefix.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

            if (! DB::table($table)->where($column, $id)->exists()) {
                return $id;
            }
        }

        throw new RuntimeException("Tidak dapat membuat ID unik untuk {$table}.");
    }
}
