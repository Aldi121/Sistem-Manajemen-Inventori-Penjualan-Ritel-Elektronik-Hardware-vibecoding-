<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RetailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_login_page_but_cannot_read_api_data(): void
    {
        $this->get('/')->assertOk()->assertSee('id="app"', false);
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_user_can_sign_in_with_the_custom_user_table(): void
    {
        $this->makeUser('ADMIN', 'admin');

        $this->postJson('/login', ['username' => 'admin', 'password' => 'test-password'])
            ->assertOk()
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonMissingPath('user.password');

        $this->getJson('/api/session')
            ->assertOk()
            ->assertJsonPath('user.role', 'ADMIN');
    }

    public function test_cashier_can_create_a_sale_with_wholesale_discount_and_cannot_manage_inventory(): void
    {
        $cashier = $this->makeUser('KASIR', 'kasir');
        $this->makeProduct(55);
        $this->actingAs($cashier);

        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/categories')->assertForbidden();

        $response = $this->postJson('/api/sales', [
            'id_pelanggan' => 'PLG00001',
            'id_barang' => 'BRG00001',
            'jumlah_beli' => 50,
        ])->assertCreated();

        $saleId = $response->json('data.id_penjualan');
        $this->assertDatabaseHas('tb_penjualan', [
            'id_penjualan' => $saleId,
            'total_bayar' => 45000,
        ]);
        $this->assertDatabaseHas('tb_barang', ['id_barang' => 'BRG00001', 'stok' => 5]);
        $this->assertDatabaseHas('tb_log_inventori', ['nama_operasi' => 'POTONG STOK']);
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_administrator_can_cancel_a_sale_and_restore_stock(): void
    {
        $admin = $this->makeUser('ADMIN', 'admin');
        $this->makeProduct(5);
        $sale = Sale::create([
            'id_penjualan' => 'TRX00001',
            'id_pelanggan' => 'PLG00001',
            'id_barang' => 'BRG00001',
            'tanggal_jual' => '2026-10-07',
            'jumlah_beli' => 2,
            'total_bayar' => 2000,
            'status_transaksi' => 'LUNAS',
        ]);
        $this->actingAs($admin);

        $this->deleteJson("/api/sales/{$sale->id_penjualan}")
            ->assertOk()
            ->assertJsonPath('message', 'Transaksi dibatalkan dan stok dipulihkan.');

        $this->assertDatabaseMissing('tb_penjualan', ['id_penjualan' => 'TRX00001']);
        $this->assertDatabaseHas('tb_barang', ['id_barang' => 'BRG00001', 'stok' => 7]);
        $this->assertDatabaseHas('tb_log_inventori', ['nama_operasi' => 'PEMULIHAN STOK']);
    }

    public function test_sale_cannot_oversell_or_decrement_stock_below_zero(): void
    {
        $cashier = $this->makeUser('KASIR', 'kasir');
        $this->makeProduct(3);
        $this->actingAs($cashier);

        $this->postJson('/api/sales', [
            'id_pelanggan' => 'PLG00001',
            'id_barang' => 'BRG00001',
            'jumlah_beli' => 4,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('jumlah_beli');

        $this->assertDatabaseMissing('tb_penjualan', ['id_barang' => 'BRG00001']);
        $this->assertDatabaseHas('tb_barang', ['id_barang' => 'BRG00001', 'stok' => 3]);
    }

    public function test_inventory_role_can_edit_catalog_but_cannot_read_sales(): void
    {
        $inventoryManager = $this->makeUser('ADMIN_BARANG', 'barang');
        $this->actingAs($inventoryManager);

        $this->postJson('/api/categories', ['nama_kategori' => 'Monitor'])
            ->assertCreated()
            ->assertJsonPath('data.nama_kategori', 'Monitor');
        $this->getJson('/api/sales')->assertForbidden();
    }

    private function makeUser(string $role, string $username): User
    {
        return User::create([
            'id_user' => 'USR00001',
            'username' => $username,
            'password' => Hash::make('test-password'),
            'nama_lengkap' => ucfirst($username).' Test',
            'role' => $role,
        ]);
    }

    private function makeProduct(int $stock): void
    {
        Category::create(['id_kategori' => 'KAT00001', 'nama_kategori' => 'Komponen']);
        Distributor::create([
            'id_distributor' => 'DST00001',
            'nama_distributor' => 'Distributor Uji',
            'kota_asal' => 'Samarinda',
            'kontak' => '081234567890',
        ]);
        Customer::create([
            'id_pelanggan' => 'PLG00001',
            'nama_pelanggan' => 'Pelanggan Uji',
            'jenis_kelamin' => 'L',
            'alamat' => 'Samarinda',
            'tanggal_daftar' => '2026-10-07',
        ]);
        Product::create([
            'id_barang' => 'BRG00001',
            'id_kategori' => 'KAT00001',
            'id_distributor' => 'DST00001',
            'nama_barang' => 'Perangkat Uji',
            'harga_satuan' => 1000,
            'stok' => $stock,
        ]);
    }
}
