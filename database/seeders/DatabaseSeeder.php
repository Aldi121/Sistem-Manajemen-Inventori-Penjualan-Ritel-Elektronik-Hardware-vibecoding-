<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedMasterData();
        $this->seedSales();
    }

    private function seedUsers(): void
    {
        $users = [
            ['id_user' => 'USR00001', 'username' => 'admin', 'nama_lengkap' => 'Administrator', 'role' => 'ADMIN', 'config' => 'admin'],
            ['id_user' => 'USR00002', 'username' => 'barang', 'nama_lengkap' => 'Admin Barang', 'role' => 'ADMIN_BARANG', 'config' => 'inventory'],
            ['id_user' => 'USR00003', 'username' => 'kasir', 'nama_lengkap' => 'Kasir Ritel', 'role' => 'KASIR', 'config' => 'cashier'],
        ];

        foreach ($users as $user) {
            if (User::where('username', $user['username'])->exists()) {
                continue;
            }

            $password = config("retail.seed_passwords.{$user['config']}");
            if (!is_string($password) || strlen($password) < 8) {
                throw new RuntimeException("Atur SEED_" . strtoupper($user['config']) . "_PASSWORD (minimal 8 karakter) di .env sebelum menjalankan db:seed.");
            }

            User::create([
                'id_user' => $user['id_user'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role' => $user['role'],
                'password' => Hash::make($password),
            ]);
        }
    }

    private function seedMasterData(): void
    {
        foreach ([
            ['KAT00001', 'Gaming'],
            ['KAT00002', 'Laptop'],
            ['KAT00003', 'Komponen PC'],
            ['KAT00004', 'Aksesoris'],
        ] as [$id, $name]) {
            Category::firstOrCreate(['id_kategori' => $id], ['nama_kategori' => $name]);
        }

        foreach ([
            ['DST00001', 'PT Teknologi Nusantara', 'Samarinda', '081234567890'],
            ['DST00002', 'CV Digital Jaya', 'Balikpapan', '081234567891'],
            ['DST00003', 'PT Hardware Indonesia', 'Jakarta', '081234567892'],
            ['DST00004', 'CV Komputer Mandiri', 'Banjarmasin', '081234567893'],
        ] as [$id, $name, $city, $contact]) {
            Distributor::firstOrCreate(['id_distributor' => $id], [
                'nama_distributor' => $name,
                'kota_asal' => $city,
                'kontak' => $contact,
            ]);
        }

        foreach ([
            ['PLG00001', 'Andi Saputra', 'L', 'Samarinda', '2026-10-01'],
            ['PLG00002', 'Budi Santoso', 'L', 'Balikpapan', '2026-10-02'],
            ['PLG00003', 'Citra Lestari', 'P', 'Samarinda', '2026-10-03'],
            ['PLG00004', 'Dina Maharani', 'P', 'Bontang', '2026-10-04'],
            ['PLG00005', 'Eko Pratama', 'L', 'Tenggarong', '2026-10-05'],
            ['PLG00006', 'Fajar Ramadhan', 'L', 'Samarinda', '2026-10-06'],
            ['PLG00007', 'Gita Permata', 'P', 'Balikpapan', '2026-10-07'],
        ] as [$id, $name, $gender, $address, $registered]) {
            Customer::firstOrCreate(['id_pelanggan' => $id], [
                'nama_pelanggan' => $name,
                'jenis_kelamin' => $gender,
                'alamat' => $address,
                'tanggal_daftar' => $registered,
            ]);
        }

        foreach ([
            ['BRG00001', 'KAT00001', 'DST00001', 'Gaming Mouse RGB', 350000, 25],
            ['BRG00002', 'KAT00001', 'DST00002', 'Gaming Keyboard Mechanical', 750000, 20],
            ['BRG00003', 'KAT00001', 'DST00003', 'Gaming Headset 7.1', 650000, 15],
            ['BRG00004', 'KAT00002', 'DST00001', 'Laptop ASUS Vivobook', 8500000, 10],
            ['BRG00005', 'KAT00002', 'DST00002', 'Laptop Lenovo IdeaPad', 7500000, 12],
            ['BRG00006', 'KAT00003', 'DST00003', 'RAM DDR4 16GB', 850000, 30],
            ['BRG00007', 'KAT00003', 'DST00003', 'SSD NVMe 1TB', 1200000, 18],
            ['BRG00008', 'KAT00003', 'DST00004', 'Power Supply 650W', 950000, 14],
            ['BRG00009', 'KAT00004', 'DST00004', 'Webcam Full HD', 550000, 20],
            ['BRG00010', 'KAT00004', 'DST00002', 'USB Hub 4 Port', 150000, 35],
        ] as [$id, $category, $distributor, $name, $price, $stock]) {
            Product::firstOrCreate(['id_barang' => $id], [
                'id_kategori' => $category,
                'id_distributor' => $distributor,
                'nama_barang' => $name,
                'harga_satuan' => $price,
                'stok' => $stock,
            ]);
        }
    }

    private function seedSales(): void
    {
        $mysqlTriggersHandleInventory = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $hadTransactions = Sale::exists();

        $rows = [
            ['TRX00002', 'PLG00002', 'BRG00002', '2026-10-08', 1, 750000],
            ['TRX00003', 'PLG00003', 'BRG00004', '2026-10-09', 1, 8500000],
            ['TRX00004', 'PLG00004', 'BRG00006', '2026-10-10', 2, 1700000],
            ['TRX00005', 'PLG00005', 'BRG00007', '2026-10-11', 1, 1200000],
            ['TRX00006', 'PLG00006', 'BRG00009', '2026-10-12', 1, 550000],
        ];

        if (!$hadTransactions && !InventoryLog::exists()) {
            $temporarySale = Sale::create([
                'id_penjualan' => 'TRX00001',
                'id_pelanggan' => 'PLG00001',
                'id_barang' => 'BRG00001',
                'tanggal_jual' => '2026-10-07',
                'jumlah_beli' => 2,
                'total_bayar' => 700000,
                'status_transaksi' => 'LUNAS',
            ]);

            if (!$mysqlTriggersHandleInventory) {
                Product::whereKey('BRG00001')->decrement('stok', 2);
                $this->recordSeedMovement('POTONG STOK', 'Barang BRG00001 berkurang sebanyak 2');
            }

            $temporarySale->delete();

            if (!$mysqlTriggersHandleInventory) {
                Product::whereKey('BRG00001')->increment('stok', 2);
                $this->recordSeedMovement('PEMULIHAN STOK', 'Barang BRG00001 dikembalikan sebanyak 2');
            }
        }

        foreach ($rows as [$id, $customer, $product, $date, $quantity, $total]) {
            if (Sale::whereKey($id)->exists()) {
                continue;
            }

            Sale::create([
                'id_penjualan' => $id,
                'id_pelanggan' => $customer,
                'id_barang' => $product,
                'tanggal_jual' => $date,
                'jumlah_beli' => $quantity,
                'total_bayar' => $total,
                'status_transaksi' => 'LUNAS',
            ]);

            if (!$mysqlTriggersHandleInventory) {
                Product::whereKey($product)->decrement('stok', $quantity);
                $this->recordSeedMovement('POTONG STOK', "Barang {$product} berkurang sebanyak {$quantity}");
            }
        }
    }

    private function recordSeedMovement(string $operation, string $details): void
    {
        InventoryLog::create([
            'nama_operasi' => $operation,
            'rincian' => $details,
            'waktu_eksekusi' => now(),
        ]);
    }
}
