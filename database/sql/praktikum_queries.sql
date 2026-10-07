-- Filter pola, rentang, dan urutan
SELECT id_barang, nama_barang, harga_satuan, stok
FROM tb_barang
WHERE nama_barang LIKE '%GAMING%'
ORDER BY harga_satuan DESC;

SELECT id_barang, nama_barang, harga_satuan
FROM tb_barang
WHERE harga_satuan BETWEEN 500000 AND 2000000
ORDER BY harga_satuan;

-- Agregasi dan HAVING
SELECT k.nama_kategori, COUNT(b.id_barang) AS jumlah_barang,
       SUM(b.stok) AS total_stok, AVG(b.harga_satuan) AS rata_harga,
       MIN(b.harga_satuan) AS harga_termurah, MAX(b.harga_satuan) AS harga_termahal
FROM tb_kategori AS k
LEFT JOIN tb_barang AS b ON b.id_kategori = k.id_kategori
GROUP BY k.id_kategori, k.nama_kategori
HAVING COUNT(b.id_barang) > 0
ORDER BY total_stok DESC;

-- INNER JOIN transaksi dengan pelanggan dan barang
SELECT p.id_penjualan, pl.nama_pelanggan, b.nama_barang,
       p.jumlah_beli, p.total_bayar, p.tanggal_jual
FROM tb_penjualan AS p
INNER JOIN tb_pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
INNER JOIN tb_barang AS b ON b.id_barang = p.id_barang
ORDER BY p.tanggal_jual DESC;

-- LEFT OUTER JOIN: barang yang belum pernah terjual
SELECT b.id_barang, b.nama_barang
FROM tb_barang AS b
LEFT OUTER JOIN tb_penjualan AS p ON p.id_barang = b.id_barang
WHERE p.id_penjualan IS NULL;

-- UNION dan UNION ALL
SELECT nama_barang AS nama, 'Barang' AS jenis FROM tb_barang
UNION
SELECT nama_kategori AS nama, 'Kategori' AS jenis FROM tb_kategori;

SELECT nama_barang AS nama, 'Barang' AS jenis FROM tb_barang
UNION ALL
SELECT nama_kategori AS nama, 'Kategori' AS jenis FROM tb_kategori;

-- Subquery scalar: barang di atas harga rata-rata
SELECT id_barang, nama_barang, harga_satuan
FROM tb_barang
WHERE harga_satuan > (SELECT AVG(harga_satuan) FROM tb_barang)
ORDER BY harga_satuan DESC;

-- Subquery multi-row IN
SELECT id_barang, nama_barang
FROM tb_barang
WHERE id_barang IN (
    SELECT id_barang FROM tb_penjualan WHERE status_transaksi = 'LUNAS'
);

-- Perbandingan ANY dan ALL
SELECT id_barang, nama_barang, harga_satuan
FROM tb_barang
WHERE harga_satuan > ANY (
    SELECT harga_satuan FROM tb_barang WHERE id_kategori = 'KAT00001'
);

SELECT id_barang, nama_barang, harga_satuan
FROM tb_barang
WHERE harga_satuan > ALL (
    SELECT harga_satuan FROM tb_barang WHERE id_kategori = 'KAT00001'
);

-- Correlated EXISTS dan NOT EXISTS
SELECT pl.id_pelanggan, pl.nama_pelanggan
FROM tb_pelanggan AS pl
WHERE EXISTS (
    SELECT 1 FROM tb_penjualan AS p WHERE p.id_pelanggan = pl.id_pelanggan
);

SELECT pl.id_pelanggan, pl.nama_pelanggan
FROM tb_pelanggan AS pl
WHERE NOT EXISTS (
    SELECT 1 FROM tb_penjualan AS p WHERE p.id_pelanggan = pl.id_pelanggan
);
