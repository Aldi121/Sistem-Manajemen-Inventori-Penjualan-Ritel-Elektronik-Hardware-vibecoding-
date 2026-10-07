# NusaTech Retail

Aplikasi full-stack **Sistem Manajemen Inventori & Penjualan Ritel Elektronik/Hardware**. Backend menggunakan Laravel 13, database MariaDB/MySQL, dan antarmuka Vue 3 melalui Vite.

## Fitur

- Login berbasis sesi dan hak akses `ADMIN`, `ADMIN_BARANG`, dan `KASIR`.
- Dashboard ringkasan stok, stok menipis, nilai inventori, dan tren penjualan.
- CRUD barang, kategori, distributor, pelanggan, dan akun pengguna sesuai batas peran.
- Transaksi kasir dengan validasi stok, diskon 10% untuk pembelian minimal 50 unit, dan pemulihan stok saat transaksi dibatalkan administrator.
- Riwayat audit pergerakan stok dan laporan penjualan.
- Migrasi mempertahankan nama tabel, kolom, relasi, dan format ID pada skema SQL tugas; data contoh di-seed dari SQL yang diberikan.
- Trigger stok/diskon/audit dan stored procedure `sp_cek_ketersediaan_stok()` dibuat otomatis pada MySQL/MariaDB.
- Script terpisah untuk demonstrasi DCL MariaDB dan kueri SQL praktikum.

## Menjalankan di Windows/XAMPP

1. Jalankan **MySQL** dari XAMPP. Buat database `db_ritel_pplg1_kel5` melalui phpMyAdmin (utf8mb4).
2. Salin `.env.example` menjadi `.env`, lalu pastikan konfigurasi `DB_*` cocok dengan XAMPP.
3. Isi `SEED_ADMIN_PASSWORD`, `SEED_INVENTORY_PASSWORD`, dan `SEED_CASHIER_PASSWORD` di `.env` dengan kata sandi lokal yang kuat (minimal 8 karakter). Jangan gunakan kata sandi tugas/contoh untuk server publik.
4. Jalankan:

   ```powershell
   composer install
   php artisan key:generate
   php artisan migrate --seed
   npm install
   ```

5. Buka dua terminal pada folder proyek:

   ```powershell
   php artisan serve
   ```

   ```powershell
   npm run dev
   ```

6. Buka `http://127.0.0.1:8000`, lalu masuk dengan username `admin`, `barang`, atau `kasir` dan kata sandi yang telah diatur di `.env`.

Jika ingin memakai dump SQL yang diberikan, impor dump tersebut **sebelum** `php artisan migrate --seed`. Migrasi akan mempertahankan tabel yang sudah ada dan menambahkan tabel `tb_pengguna`; seeder menghindari penggandaan data contoh. Saat mulai dari database kosong, jalankan migrasi dan seeder tanpa mengimpor dump.

## Hak akses

| Peran aplikasi | Akses |
| --- | --- |
| `ADMIN` | Semua menu, pengelolaan pengguna, laporan, dan pembatalan transaksi |
| `ADMIN_BARANG` | Barang, kategori, distributor, dan penyesuaian stok |
| `KASIR` | Membaca barang/pelanggan, membuat transaksi, dan melihat laporan penjualan |

Hak akses aplikasi ditegakkan pada middleware/rute Laravel. `database/sql/roles.sql` berisi akun MariaDB terpisah untuk demonstrasi `CREATE USER`/`GRANT` pada tugas. Ganti semua placeholder `CHANGE_ME_*` sebelum menjalankan script. Jangan memakai akun MariaDB kasir/admin barang tersebut sebagai koneksi layanan Laravel karena aplikasi memerlukan satu akun layanan; jangan beri akun layanan itu kepada pengguna akhir.

## Praktikum SQL

- `database/sql/roles.sql`: contoh pembagian hak akses DCL untuk akun MariaDB lokal.
- `database/sql/praktikum_queries.sql`: contoh `LIKE`, `BETWEEN`, agregasi, `JOIN`, `UNION`, subquery `IN`/`ANY`/`ALL`, serta `EXISTS`/`NOT EXISTS`.
- Stored procedure dan trigger dibuat oleh migrasi pada koneksi MySQL/MariaDB. Uji prosedur dengan `CALL sp_cek_ketersediaan_stok('BRG00001', 2);`.

## Pengujian

```powershell
php artisan test
npm run build
```
