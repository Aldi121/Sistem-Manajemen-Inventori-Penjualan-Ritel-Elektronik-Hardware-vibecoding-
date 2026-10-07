<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const user = ref(null);
const loading = ref(true);
const busy = ref(false);
const page = ref('dashboard');
const search = ref('');
const searchCategory = ref('');
const notice = ref('');
const noticeIsError = ref(false);
const dashboard = ref(null);
const products = ref([]);
const categories = ref([]);
const distributors = ref([]);
const customers = ref([]);
const sales = ref([]);
const users = ref([]);
const logs = ref([]);
const report = ref(null);
const reportFrom = ref('');
const reportTo = ref('');
const modal = reactive({ open: false, kind: '', title: '', id: null, data: {} });
const login = reactive({ username: '', password: '' });
const loginError = ref('');

const menus = [
    { id: 'dashboard', label: 'Ringkasan', icon: '⌂', roles: ['ADMIN', 'ADMIN_BARANG', 'KASIR'] },
    { id: 'products', label: 'Barang', icon: '▦', roles: ['ADMIN', 'ADMIN_BARANG', 'KASIR'] },
    { id: 'sales', label: 'Penjualan', icon: '↗', roles: ['ADMIN', 'KASIR'] },
    { id: 'customers', label: 'Pelanggan', icon: '♙', roles: ['ADMIN', 'KASIR'] },
    { id: 'categories', label: 'Kategori', icon: '◈', roles: ['ADMIN', 'ADMIN_BARANG'] },
    { id: 'distributors', label: 'Distributor', icon: '⌁', roles: ['ADMIN', 'ADMIN_BARANG'] },
    { id: 'users', label: 'Pengguna', icon: '♧', roles: ['ADMIN'] },
    { id: 'logs', label: 'Audit stok', icon: '≋', roles: ['ADMIN'] },
    { id: 'reports', label: 'Laporan', icon: '▤', roles: ['ADMIN', 'KASIR'] },
];
const visibleMenus = computed(() => menus.filter((item) => user.value && item.roles.includes(user.value.role)));
const visibleProducts = computed(() => filteredProducts.value.filter((product) => !searchCategory.value || product.id_kategori === searchCategory.value));
const pageTitles = {
    dashboard: 'Ringkasan',
    products: 'Manajemen Barang',
    sales: 'Penjualan',
    customers: 'Pelanggan',
    categories: 'Kategori Barang',
    distributors: 'Distributor',
    users: 'Pengguna & Hak Akses',
    logs: 'Audit Pergerakan Stok',
    reports: 'Laporan Penjualan',
};
const filteredProducts = computed(() => products.value.filter((item) => searchable(`${item.nama_barang} ${item.id_barang} ${item.category?.nama_kategori || ''}`)));
const filteredSales = computed(() => sales.value.filter((item) => searchable(`${item.id_penjualan} ${item.customer?.nama_pelanggan} ${item.product?.nama_barang}`)));
const filteredCustomers = computed(() => customers.value.filter((item) => searchable(`${item.nama_pelanggan} ${item.alamat} ${item.id_pelanggan}`)));
const filteredCategories = computed(() => categories.value.filter((item) => searchable(`${item.nama_kategori} ${item.id_kategori}`)));
const filteredDistributors = computed(() => distributors.value.filter((item) => searchable(`${item.nama_distributor} ${item.kota_asal} ${item.kontak}`)));
const filteredUsers = computed(() => users.value.filter((item) => searchable(`${item.nama_lengkap} ${item.username} ${item.role}`)));
const filteredLogs = computed(() => logs.value.filter((item) => searchable(`${item.nama_operasi} ${item.rincian}`)));
const canManageInventory = computed(() => ['ADMIN', 'ADMIN_BARANG'].includes(user.value?.role));
const canManageCustomers = computed(() => user.value?.role === 'ADMIN');
const canManageUsers = computed(() => user.value?.role === 'ADMIN');
const roleName = computed(() => ({ ADMIN: 'Administrator', ADMIN_BARANG: 'Admin Barang', KASIR: 'Kasir' }[user.value?.role] || ''));
const initials = computed(() => (user.value?.nama_lengkap || 'U').split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase());

function searchable(value) {
    return !search.value || String(value || '').toLowerCase().includes(search.value.toLowerCase());
}

function rupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
}

function number(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Makassar' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00+08:00`));
}

function dateTimeLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Makassar' }).format(new Date(`${value.replace(' ', 'T')}+08:00`));
}

function stockClass(stock) {
    return stock <= 0 ? 'out' : stock <= 5 ? 'low' : '';
}

async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...options.headers,
    };
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    const response = await fetch(path, { credentials: 'same-origin', ...options, headers });
    const body = response.status === 204 ? {} : await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 401) user.value = null;
        const message = body.message || Object.values(body.errors || {}).flat()[0] || 'Permintaan tidak dapat diproses.';
        throw new Error(message);
    }

    return body;
}

function toast(message, isError = false) {
    notice.value = message;
    noticeIsError.value = isError;
    window.setTimeout(() => {
        if (notice.value === message) notice.value = '';
    }, 3200);
}

async function submitLogin() {
    loginError.value = '';
    busy.value = true;
    try {
        const result = await api('/login', { method: 'POST', body: JSON.stringify(login) });
        user.value = result.user;
        login.password = '';
        await loadPage('dashboard');
    } catch (error) {
        loginError.value = error.message;
    } finally {
        busy.value = false;
        loading.value = false;
    }
}

async function logout() {
    try {
        const result = await api('/logout', { method: 'POST' });
        document.querySelector('meta[name="csrf-token"]').content = result.csrf_token;
        user.value = null;
        page.value = 'dashboard';
        dashboard.value = null;
    } catch (error) {
        toast(error.message, true);
    }
}

async function bootstrap() {
    try {
        const result = await api('/api/session');
        user.value = result.user;
        await loadPage('dashboard');
    } catch (error) {
        if (error.message !== 'Unauthenticated.') toast(error.message, true);
    } finally {
        loading.value = false;
    }
}

async function loadPage(target) {
    page.value = target;
    search.value = '';
    busy.value = true;
    try {
        if (target === 'dashboard') dashboard.value = await api('/api/dashboard');
        if (target === 'products') {
            const result = await api('/api/products');
            products.value = result.data;
            if (canManageInventory.value) {
                const [categoryResult, distributorResult] = await Promise.all([api('/api/categories'), api('/api/distributors')]);
                categories.value = categoryResult.data;
                distributors.value = distributorResult.data;
            }
        }
        if (target === 'sales') {
            const [saleResult, customerResult, productResult] = await Promise.all([api('/api/sales'), api('/api/customers'), api('/api/products')]);
            sales.value = saleResult.data;
            customers.value = customerResult.data;
            products.value = productResult.data;
        }
        if (target === 'customers') customers.value = (await api('/api/customers')).data;
        if (target === 'categories') categories.value = (await api('/api/categories')).data;
        if (target === 'distributors') distributors.value = (await api('/api/distributors')).data;
        if (target === 'users') users.value = (await api('/api/users')).data;
        if (target === 'logs') logs.value = (await api('/api/inventory-logs')).data;
        if (target === 'reports') await loadReport();
    } catch (error) {
        toast(error.message, true);
    } finally {
        busy.value = false;
    }
}

async function loadReport() {
    const query = new URLSearchParams();
    if (reportFrom.value) query.set('from', reportFrom.value);
    if (reportTo.value) query.set('to', reportTo.value);
    report.value = await api(`/api/reports${query.size ? `?${query}` : ''}`);
}

function openCreate(kind, id = null) {
    const defaults = {
        product: { nama_barang: '', id_kategori: categories.value[0]?.id_kategori || '', id_distributor: distributors.value[0]?.id_distributor || '', harga_satuan: '', stok: 0 },
        category: { nama_kategori: '' },
        distributor: { nama_distributor: '', kota_asal: '', kontak: '' },
        customer: { nama_pelanggan: '', jenis_kelamin: 'L', alamat: '' },
        user: { username: '', nama_lengkap: '', role: 'KASIR', password: '' },
        sale: { id_pelanggan: customers.value[0]?.id_pelanggan || '', id_barang: products.value[0]?.id_barang || '', jumlah_beli: 1 },
        stock: { operasi: 'tambah', jumlah: 1 },
    };
    const titles = { product: 'Tambah barang', category: 'Tambah kategori', distributor: 'Tambah distributor', customer: 'Tambah pelanggan', user: 'Tambah pengguna', sale: 'Transaksi penjualan', stock: 'Penyesuaian stok' };
    Object.assign(modal, { open: true, kind, title: titles[kind], id, data: { ...defaults[kind] } });
}

function openEdit(kind, item) {
    const fields = {
        product: ['nama_barang', 'id_kategori', 'id_distributor', 'harga_satuan', 'stok'],
        category: ['nama_kategori'],
        distributor: ['nama_distributor', 'kota_asal', 'kontak'],
        customer: ['nama_pelanggan', 'jenis_kelamin', 'alamat'],
        user: ['username', 'nama_lengkap', 'role'],
    };
    const key = { product: 'id_barang', category: 'id_kategori', distributor: 'id_distributor', customer: 'id_pelanggan', user: 'id_user' }[kind];
    const titles = { product: 'Ubah barang', category: 'Ubah kategori', distributor: 'Ubah distributor', customer: 'Ubah pelanggan', user: 'Ubah pengguna' };
    const data = Object.fromEntries(fields[kind].map((field) => [field, item[field]]));
    if (kind === 'user') data.password = '';
    Object.assign(modal, { open: true, kind, title: titles[kind], id: item[key], data });
}

function closeModal() {
    modal.open = false;
}

async function saveModal() {
    busy.value = true;
    try {
        const { kind, id, data } = modal;
        if (kind === 'sale') {
            await api('/api/sales', { method: 'POST', body: JSON.stringify(data) });
            toast('Transaksi tersimpan dan stok diperbarui.');
            closeModal();
            await loadPage('sales');
            return;
        }
        if (kind === 'stock') {
            await api(`/api/products/${encodeURIComponent(id)}/stock`, { method: 'POST', body: JSON.stringify(data) });
            toast('Stok berhasil disesuaikan.');
            closeModal();
            await loadPage('products');
            return;
        }
        const endpoints = { product: 'products', category: 'categories', distributor: 'distributors', customer: 'customers', user: 'users' };
        const endpoint = `/api/${endpoints[kind]}${id ? `/${encodeURIComponent(id)}` : ''}`;
        await api(endpoint, { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
        toast(`${modal.title.replace('Ubah ', '').replace('Tambah ', '')} berhasil disimpan.`);
        closeModal();
        await loadPage(page.value);
    } catch (error) {
        toast(error.message, true);
    } finally {
        busy.value = false;
    }
}

async function removeItem(kind, item) {
    const config = {
        product: { endpoint: 'products', id: item.id_barang, name: item.nama_barang },
        category: { endpoint: 'categories', id: item.id_kategori, name: item.nama_kategori },
        distributor: { endpoint: 'distributors', id: item.id_distributor, name: item.nama_distributor },
        customer: { endpoint: 'customers', id: item.id_pelanggan, name: item.nama_pelanggan },
        user: { endpoint: 'users', id: item.id_user, name: item.nama_lengkap },
    }[kind];
    if (!window.confirm(`Hapus ${config.name}? Tindakan ini tidak dapat dibatalkan.`)) return;
    try {
        await api(`/api/${config.endpoint}/${encodeURIComponent(config.id)}`, { method: 'DELETE' });
        toast('Data berhasil dihapus.');
        await loadPage(page.value);
    } catch (error) {
        toast(error.message, true);
    }
}

async function cancelSale(sale) {
    if (!window.confirm(`Batalkan transaksi ${sale.id_penjualan} dan pulihkan stok barang?`)) return;
    try {
        await api(`/api/sales/${encodeURIComponent(sale.id_penjualan)}`, { method: 'DELETE' });
        toast('Transaksi dibatalkan; stok telah dipulihkan.');
        await loadPage('sales');
    } catch (error) {
        toast(error.message, true);
    }
}

function productName(id) {
    return products.value.find((item) => item.id_barang === id)?.nama_barang || id || 'Pilih barang';
}

function maxChartValue() {
    return Math.max(1, ...(dashboard.value?.sales_chart || []).map((item) => item.revenue));
}

onMounted(bootstrap);
</script>

<template>
    <div v-if="loading" class="loading">Memuat aplikasi...</div>

    <main v-else-if="!user" class="login-page">
        <section class="login-card">
            <div class="login-brand">
                <span class="brand-mark">N</span>
                <div>
                    <div class="brand-name" style="color: #182740">NusaTech Retail</div>
                    <div class="brand-caption">Sistem inventori & penjualan</div>
                </div>
            </div>
            <h1>Selamat datang</h1>
            <p>Masuk untuk mengelola inventori, pelanggan, dan transaksi penjualan toko.</p>
            <div v-if="loginError" class="login-alert">{{ loginError }}</div>
            <form class="login-form" @submit.prevent="submitLogin">
                <label class="field-wrap">
                    <span class="field-label">Username</span>
                    <input v-model.trim="login.username" class="field" autocomplete="username" maxlength="30" required placeholder="Masukkan username">
                </label>
                <label class="field-wrap">
                    <span class="field-label">Kata sandi</span>
                    <input v-model="login.password" class="field" type="password" autocomplete="current-password" required placeholder="Masukkan kata sandi">
                </label>
                <button class="button" type="submit" :disabled="busy">{{ busy ? 'Memproses...' : 'Masuk ke aplikasi' }}</button>
            </form>
            <div class="login-foot">Akses akun ditentukan oleh peran Administrator, Admin Barang, atau Kasir.</div>
        </section>
    </main>

    <div v-else class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">N</span>
                <div>
                    <div class="brand-name">NusaTech Retail</div>
                    <div class="brand-caption">Inventory management</div>
                </div>
            </div>
            <div class="nav-label">Menu utama</div>
            <nav class="nav-list" aria-label="Menu utama">
                <button v-for="item in visibleMenus" :key="item.id" class="nav-item" :class="{ active: page === item.id }" @click="loadPage(item.id)">
                    <span class="nav-icon">{{ item.icon }}</span><span>{{ item.label }}</span>
                </button>
            </nav>
            <div class="side-foot">
                <span>{{ user.nama_lengkap }}</span>
                <small>{{ roleName }} · {{ user.username }}</small>
            </div>
        </aside>

        <section class="main-area">
            <header class="topbar">
                <div>
                    <div class="crumb">NusaTech Retail / {{ pageTitles[page] }}</div>
                    <div class="top-title">{{ pageTitles[page] }}</div>
                </div>
                <div class="top-right">
                    <span class="date-today">{{ new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Makassar' }).format(new Date()) }}</span>
                    <div class="profile">
                        <span class="avatar">{{ initials }}</span>
                        <div><div class="profile-name">{{ user.nama_lengkap }}</div><div class="profile-role">{{ roleName }}</div></div>
                    </div>
                    <button class="button ghost small" @click="logout">Keluar</button>
                </div>
            </header>

            <main class="content">
                <div v-if="page === 'dashboard'">
                    <div class="page-heading">
                        <div><h1>Selamat datang, {{ user.nama_lengkap.split(' ')[0] }} 👋</h1><p>Berikut ringkasan kondisi toko hari ini.</p></div>
                        <button v-if="user.role !== 'ADMIN_BARANG'" class="button" @click="loadPage('sales')">Lihat transaksi <span>→</span></button>
                    </div>
                    <div v-if="dashboard" class="stats-grid">
                        <article class="stat-card"><div class="stat-top"><span class="stat-label">Jenis barang</span><span class="stat-icon">▦</span></div><div class="stat-value">{{ number(dashboard.summary.products) }}</div><div class="stat-note">Produk terdaftar</div></article>
                        <article class="stat-card"><div class="stat-top"><span class="stat-label">Total stok</span><span class="stat-icon green">⌁</span></div><div class="stat-value">{{ number(dashboard.summary.stock_units) }}</div><div class="stat-note">{{ number(dashboard.summary.low_stock) }} barang perlu perhatian</div></article>
                        <article v-if="dashboard.sales" class="stat-card"><div class="stat-top"><span class="stat-label">Penjualan hari ini</span><span class="stat-icon orange">↗</span></div><div class="stat-value">{{ rupiah(dashboard.sales.today_revenue) }}</div><div class="stat-note">{{ number(dashboard.sales.today_count) }} transaksi hari ini</div></article>
                        <article v-else class="stat-card"><div class="stat-top"><span class="stat-label">Nilai persediaan</span><span class="stat-icon">◈</span></div><div class="stat-value">{{ rupiah(dashboard.summary.inventory_value) }}</div><div class="stat-note">Harga beli × jumlah stok</div></article>
                        <article v-if="dashboard.sales" class="stat-card"><div class="stat-top"><span class="stat-label">Pendapatan bulan ini</span><span class="stat-icon green">Rp</span></div><div class="stat-value">{{ rupiah(dashboard.sales.month_revenue) }}</div><div class="stat-note">Total seluruh transaksi</div></article>
                        <article v-else class="stat-card"><div class="stat-top"><span class="stat-label">Stok habis</span><span class="stat-icon red">!</span></div><div class="stat-value">{{ number(dashboard.summary.out_of_stock) }}</div><div class="stat-note">Produk perlu di-restok</div></article>
                    </div>
                    <div v-if="dashboard" class="dashboard-grid">
                        <section v-if="dashboard.sales" class="panel">
                            <div class="panel-head"><div><div class="panel-title">Tren penjualan</div><div class="panel-caption">Pendapatan enam bulan terakhir</div></div><span class="badge">6 bulan</span></div>
                            <div class="chart">
                                <div v-for="(item, index) in dashboard.sales_chart" :key="item.month" class="chart-column">
                                    <span class="chart-value">{{ item.revenue ? rupiah(item.revenue).replace('Rp', 'Rp ') : '—' }}</span>
                                    <div class="chart-bar-wrap"><div class="chart-bar" :class="{ current: index === dashboard.sales_chart.length - 1 }" :style="{ height: `${Math.max(4, item.revenue / maxChartValue() * 100)}%` }"></div></div>
                                    <span class="chart-label">{{ new Intl.DateTimeFormat('id-ID', { month: 'short', timeZone: 'Asia/Makassar' }).format(new Date(`${item.month}-01T00:00:00+08:00`)) }}</span>
                                </div>
                            </div>
                        </section>
                        <section class="panel">
                            <div class="panel-head"><div><div class="panel-title">Stok menipis</div><div class="panel-caption">Stok 5 unit atau kurang</div></div><button class="button ghost small" @click="loadPage('products')">Semua →</button></div>
                            <div v-if="dashboard.low_stock_products.length" class="stock-list">
                                <div v-for="item in dashboard.low_stock_products" :key="item.id_barang" class="stock-item"><span class="stock-dot"></span><div class="stock-name">{{ item.nama_barang }}<div class="stock-category">{{ item.category?.nama_kategori || item.id_barang }}</div></div><span class="stock-count">{{ item.stok }} unit</span></div>
                            </div>
                            <div v-else class="empty"><strong>Stok aman</strong>Tidak ada barang dengan stok menipis.</div>
                        </section>
                    </div>
                    <section v-if="dashboard?.sales" class="panel">
                        <div class="panel-head"><div><div class="panel-title">Transaksi terbaru</div><div class="panel-caption">Aktivitas penjualan terakhir</div></div><button class="button ghost small" @click="loadPage('sales')">Lihat semua →</button></div>
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>No. transaksi</th><th>Pelanggan</th><th>Barang</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead><tbody><tr v-for="sale in dashboard.recent_sales" :key="sale.id_penjualan"><td class="cell-main">{{ sale.id_penjualan }}</td><td>{{ sale.customer?.nama_pelanggan }}</td><td>{{ sale.product?.nama_barang }} <span class="cell-sub">× {{ sale.jumlah_beli }}</span></td><td>{{ dateLabel(sale.tanggal_jual) }}</td><td class="money">{{ rupiah(sale.total_bayar) }}</td><td><span class="badge">{{ sale.status_transaksi }}</span></td></tr></tbody></table></div>
                    </section>
                </div>

                <div v-else-if="page === 'products'">
                    <div class="page-heading"><div><h1>Barang</h1><p>Kelola daftar produk elektronik dan pantau ketersediaan stok.</p></div><button v-if="canManageInventory" class="button" @click="openCreate('product')">＋ Tambah barang</button></div>
                    <div class="panel">
                        <div class="panel-head"><div class="filters"><input v-model="search" class="searchbox" placeholder="Cari nama atau kode barang..."><select v-if="canManageInventory" v-model="searchCategory" class="selectbox"><option value="">Semua kategori</option><option v-for="category in categories" :key="category.id_kategori" :value="category.id_kategori">{{ category.nama_kategori }}</option></select></div><span class="panel-caption">{{ filteredProducts.length }} barang</span></div>
                        <div v-if="busy" class="loading">Memuat data...</div>
                        <div v-else-if="visibleProducts.length" class="table-wrap"><table class="data-table"><thead><tr><th>Barang</th><th>Kategori</th><th>Distributor</th><th>Harga satuan</th><th>Stok</th><th v-if="canManageInventory">Aksi</th></tr></thead><tbody><tr v-for="item in visibleProducts" :key="item.id_barang"><td><div class="cell-main">{{ item.nama_barang }}</div><div class="cell-sub">{{ item.id_barang }}</div></td><td>{{ item.category?.nama_kategori || item.id_kategori }}</td><td>{{ item.distributor?.nama_distributor || item.id_distributor }}</td><td class="money">{{ rupiah(item.harga_satuan) }}</td><td><span class="badge" :class="stockClass(item.stok)">{{ item.stok }} unit</span></td><td v-if="canManageInventory"><div class="row-actions"><button class="button secondary small" @click="openCreate('stock', item.id_barang)">Stok</button><button class="button ghost small" aria-label="Ubah barang" @click="openEdit('product', item)">Ubah</button><button class="button ghost small" aria-label="Hapus barang" @click="removeItem('product', item)">Hapus</button></div></td></tr></tbody></table></div>
                        <div v-else class="empty"><strong>Barang belum tersedia</strong>Ubah kata kunci atau tambahkan barang baru.</div>
                    </div>
                </div>

                <div v-else-if="page === 'sales'">
                    <div class="page-heading"><div><h1>Penjualan</h1><p>Catat pembayaran baru dan telusuri riwayat transaksi.</p></div><button class="button" @click="openCreate('sale')">＋ Transaksi baru</button></div>
                    <div class="notice">Setiap transaksi otomatis mengurangi stok. Pembelian 50 unit atau lebih mendapatkan diskon grosir 10%.</div>
                    <div class="panel">
                        <div class="panel-head"><input v-model="search" class="searchbox" placeholder="Cari transaksi, pelanggan, barang..."><span class="panel-caption">{{ filteredSales.length }} transaksi terakhir</span></div>
                        <div v-if="filteredSales.length" class="table-wrap"><table class="data-table"><thead><tr><th>No. transaksi</th><th>Pelanggan</th><th>Barang</th><th>Tanggal</th><th>Total pembayaran</th><th>Status</th><th v-if="user.role === 'ADMIN'">Aksi</th></tr></thead><tbody><tr v-for="sale in filteredSales" :key="sale.id_penjualan"><td class="cell-main">{{ sale.id_penjualan }}</td><td>{{ sale.customer?.nama_pelanggan }}</td><td><div>{{ sale.product?.nama_barang }}</div><div class="cell-sub">{{ sale.jumlah_beli }} unit</div></td><td>{{ dateLabel(sale.tanggal_jual) }}</td><td class="money">{{ rupiah(sale.total_bayar) }}</td><td><span class="badge">{{ sale.status_transaksi }}</span></td><td v-if="user.role === 'ADMIN'"><button class="button danger small" @click="cancelSale(sale)">Batalkan</button></td></tr></tbody></table></div>
                        <div v-else class="empty"><strong>Belum ada transaksi</strong>Buat transaksi penjualan pertama.</div>
                    </div>
                </div>

                <div v-else-if="page === 'customers'">
                    <div class="page-heading"><div><h1>Pelanggan</h1><p>Data pelanggan toko dan riwayat pendaftaran.</p></div><button v-if="canManageCustomers" class="button" @click="openCreate('customer')">＋ Tambah pelanggan</button></div>
                    <div class="panel"><div class="panel-head"><input v-model="search" class="searchbox" placeholder="Cari nama, kode, atau alamat..."><span class="panel-caption">{{ filteredCustomers.length }} pelanggan</span></div>
                        <div v-if="filteredCustomers.length" class="table-wrap"><table class="data-table"><thead><tr><th>Pelanggan</th><th>Jenis kelamin</th><th>Alamat</th><th>Tanggal daftar</th><th v-if="canManageCustomers">Aksi</th></tr></thead><tbody><tr v-for="customer in filteredCustomers" :key="customer.id_pelanggan"><td><div class="cell-main">{{ customer.nama_pelanggan }}</div><div class="cell-sub">{{ customer.id_pelanggan }}</div></td><td>{{ customer.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</td><td>{{ customer.alamat }}</td><td>{{ dateLabel(customer.tanggal_daftar) }}</td><td v-if="canManageCustomers"><div class="row-actions"><button class="button ghost small" @click="openEdit('customer', customer)">Ubah</button><button class="button ghost small" @click="removeItem('customer', customer)">Hapus</button></div></td></tr></tbody></table></div><div v-else class="empty"><strong>Belum ada pelanggan</strong></div>
                    </div>
                </div>

                <div v-else-if="page === 'categories'">
                    <div class="page-heading"><div><h1>Kategori</h1><p>Kelompokkan barang agar mudah dicari dan dilaporkan.</p></div><button class="button" @click="openCreate('category')">＋ Tambah kategori</button></div>
                    <div class="panel"><div class="panel-head"><input v-model="search" class="searchbox" placeholder="Cari kategori..."><span class="panel-caption">{{ filteredCategories.length }} kategori</span></div>
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Kategori</th><th>Kode</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in filteredCategories" :key="item.id_kategori"><td class="cell-main">{{ item.nama_kategori }}</td><td>{{ item.id_kategori }}</td><td><div class="row-actions"><button class="button ghost small" @click="openEdit('category', item)">Ubah</button><button class="button ghost small" @click="removeItem('category', item)">Hapus</button></div></td></tr></tbody></table></div><div v-if="!filteredCategories.length" class="empty">Kategori belum tersedia.</div>
                    </div>
                </div>

                <div v-else-if="page === 'distributors'">
                    <div class="page-heading"><div><h1>Distributor</h1><p>Kelola pemasok dan informasi kontak.</p></div><button class="button" @click="openCreate('distributor')">＋ Tambah distributor</button></div>
                    <div class="panel"><div class="panel-head"><input v-model="search" class="searchbox" placeholder="Cari nama, kota, kontak..."><span class="panel-caption">{{ filteredDistributors.length }} distributor</span></div>
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Nama distributor</th><th>Kota asal</th><th>Kontak</th><th>Kode</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in filteredDistributors" :key="item.id_distributor"><td class="cell-main">{{ item.nama_distributor }}</td><td>{{ item.kota_asal }}</td><td>{{ item.kontak }}</td><td>{{ item.id_distributor }}</td><td><div class="row-actions"><button class="button ghost small" @click="openEdit('distributor', item)">Ubah</button><button class="button ghost small" @click="removeItem('distributor', item)">Hapus</button></div></td></tr></tbody></table></div><div v-if="!filteredDistributors.length" class="empty">Distributor belum tersedia.</div>
                    </div>
                </div>

                <div v-else-if="page === 'users'">
                    <div class="page-heading"><div><h1>Pengguna & hak akses</h1><p>Atur akun dan batas akses untuk setiap peran sistem.</p></div><button class="button" @click="openCreate('user')">＋ Tambah pengguna</button></div>
                    <div class="notice"><strong>ADMIN</strong>: akses penuh · <strong>ADMIN BARANG</strong>: barang, kategori, distributor · <strong>KASIR</strong>: penjualan dan baca barang/pelanggan.</div>
                    <div class="panel"><div class="panel-head"><input v-model="search" class="searchbox" placeholder="Cari username atau nama..."><span class="panel-caption">{{ filteredUsers.length }} pengguna</span></div>
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Pengguna</th><th>Username</th><th>Peran</th><th>ID</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in filteredUsers" :key="item.id_user"><td class="cell-main">{{ item.nama_lengkap }}</td><td>{{ item.username }}</td><td><span class="badge" :class="{ low: item.role === 'ADMIN_BARANG' }">{{ { ADMIN: 'Administrator', ADMIN_BARANG: 'Admin Barang', KASIR: 'Kasir' }[item.role] }}</span></td><td>{{ item.id_user }}</td><td><div class="row-actions"><button class="button ghost small" @click="openEdit('user', item)">Ubah</button><button class="button ghost small" @click="removeItem('user', item)">Hapus</button></div></td></tr></tbody></table></div>
                    </div>
                </div>

                <div v-else-if="page === 'logs'">
                    <div class="page-heading"><div><h1>Audit pergerakan stok</h1><p>Riwayat otomatis dari transaksi dan penyesuaian inventori.</p></div></div>
                    <div class="panel"><div class="panel-head"><input v-model="search" class="searchbox" placeholder="Cari operasi atau kode barang..."><span class="panel-caption">{{ filteredLogs.length }} catatan</span></div>
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Waktu</th><th>Operasi</th><th>Rincian</th><th>ID log</th></tr></thead><tbody><tr v-for="item in filteredLogs" :key="item.id_log"><td>{{ dateTimeLabel(item.waktu_eksekusi) }}</td><td><span class="badge" :class="{ low: item.nama_operasi !== 'POTONG STOK' }">{{ item.nama_operasi }}</span></td><td>{{ item.rincian }}</td><td>{{ item.id_log }}</td></tr></tbody></table></div><div v-if="!filteredLogs.length" class="empty">Belum ada catatan pergerakan stok.</div>
                    </div>
                </div>

                <div v-else-if="page === 'reports'">
                    <div class="page-heading"><div><h1>Laporan penjualan</h1><p>Ringkasan transaksi dan produk terlaris.</p></div><div class="filters"><input v-model="reportFrom" type="date" class="datebox"><input v-model="reportTo" type="date" class="datebox"><button class="button" @click="loadReport">Terapkan</button></div></div>
                    <div v-if="report" class="report-cards"><div class="report-card"><span>Jumlah transaksi</span><strong>{{ number(report.summary.transactions) }}</strong></div><div class="report-card"><span>Unit terjual</span><strong>{{ number(report.summary.units) }}</strong></div><div class="report-card"><span>Total pendapatan</span><strong>{{ rupiah(report.summary.revenue) }}</strong></div><div class="report-card"><span>Rata-rata transaksi</span><strong>{{ rupiah(report.summary.average_transaction) }}</strong></div></div>
                    <div v-if="report" class="dashboard-grid"><section class="panel"><div class="panel-head"><div class="panel-title">Produk berdasarkan pendapatan</div></div><div v-if="report.top_products.length" class="table-wrap"><table class="data-table"><thead><tr><th>Produk</th><th>Unit terjual</th><th>Pendapatan</th></tr></thead><tbody><tr v-for="item in report.top_products" :key="item.product"><td class="cell-main">{{ item.product }}</td><td>{{ number(item.units) }}</td><td class="money">{{ rupiah(item.revenue) }}</td></tr></tbody></table></div><div v-else class="empty">Belum ada data pada periode ini.</div></section><section class="panel"><div class="panel-head"><div class="panel-title">Periode laporan</div></div><div class="stock-list"><div class="stock-item"><span class="stock-name">Mulai</span><span>{{ reportFrom || 'Semua tanggal' }}</span></div><div class="stock-item"><span class="stock-name">Sampai</span><span>{{ reportTo || 'Hari ini' }}</span></div><div class="stock-item"><span class="stock-name">Catatan</span><span>Transaksi lunas</span></div></div></section></div>
                </div>
            </main>
        </section>

        <div v-if="modal.open" class="modal-backdrop" @click.self="closeModal">
            <section class="modal" role="dialog" aria-modal="true" :aria-label="modal.title">
                <div class="modal-head"><div><h2>{{ modal.title }}</h2><p>Lengkapi informasi di bawah ini.</p></div><button class="button ghost small" aria-label="Tutup" @click="closeModal">✕</button></div>
                <form @submit.prevent="saveModal">
                    <div class="form-grid">
                        <template v-if="modal.kind === 'product'">
                            <label class="field-wrap full"><span class="field-label">Nama barang</span><input v-model.trim="modal.data.nama_barang" class="field" maxlength="50" required></label>
                            <label class="field-wrap"><span class="field-label">Kategori</span><select v-model="modal.data.id_kategori" class="field" required><option v-for="item in categories" :key="item.id_kategori" :value="item.id_kategori">{{ item.nama_kategori }}</option></select></label>
                            <label class="field-wrap"><span class="field-label">Distributor</span><select v-model="modal.data.id_distributor" class="field" required><option v-for="item in distributors" :key="item.id_distributor" :value="item.id_distributor">{{ item.nama_distributor }}</option></select></label>
                            <label class="field-wrap"><span class="field-label">Harga jual satuan (Rp)</span><input v-model.number="modal.data.harga_satuan" class="field" type="number" min="0" required></label>
                            <label class="field-wrap"><span class="field-label">Jumlah stok</span><input v-model.number="modal.data.stok" class="field" type="number" min="0" required></label>
                        </template>
                        <template v-else-if="modal.kind === 'category'"><label class="field-wrap full"><span class="field-label">Nama kategori</span><input v-model.trim="modal.data.nama_kategori" class="field" maxlength="30" required></label></template>
                        <template v-else-if="modal.kind === 'distributor'">
                            <label class="field-wrap full"><span class="field-label">Nama distributor</span><input v-model.trim="modal.data.nama_distributor" class="field" maxlength="50" required></label>
                            <label class="field-wrap"><span class="field-label">Kota asal</span><input v-model.trim="modal.data.kota_asal" class="field" maxlength="30" required></label>
                            <label class="field-wrap"><span class="field-label">Nomor kontak</span><input v-model.trim="modal.data.kontak" class="field" maxlength="15" required></label>
                        </template>
                        <template v-else-if="modal.kind === 'customer'">
                            <label class="field-wrap full"><span class="field-label">Nama pelanggan</span><input v-model.trim="modal.data.nama_pelanggan" class="field" maxlength="50" required></label>
                            <label class="field-wrap"><span class="field-label">Jenis kelamin</span><select v-model="modal.data.jenis_kelamin" class="field"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></label>
                            <label class="field-wrap"><span class="field-label">Alamat / kota</span><input v-model.trim="modal.data.alamat" class="field" maxlength="100" required></label>
                        </template>
                        <template v-else-if="modal.kind === 'user'">
                            <label class="field-wrap"><span class="field-label">Username</span><input v-model.trim="modal.data.username" class="field" maxlength="30" autocomplete="off" required></label>
                            <label class="field-wrap"><span class="field-label">Nama lengkap</span><input v-model.trim="modal.data.nama_lengkap" class="field" maxlength="50" required></label>
                            <label class="field-wrap"><span class="field-label">Peran</span><select v-model="modal.data.role" class="field"><option value="ADMIN">Administrator</option><option value="ADMIN_BARANG">Admin Barang</option><option value="KASIR">Kasir</option></select></label>
                            <label class="field-wrap"><span class="field-label">Kata sandi {{ modal.id ? '(kosongkan bila tetap)' : '' }}</span><input v-model="modal.data.password" class="field" type="password" :required="!modal.id" minlength="8" maxlength="72" autocomplete="new-password"></label>
                        </template>
                        <template v-else-if="modal.kind === 'sale'">
                            <label class="field-wrap full"><span class="field-label">Pelanggan</span><select v-model="modal.data.id_pelanggan" class="field" required><option v-for="item in customers" :key="item.id_pelanggan" :value="item.id_pelanggan">{{ item.nama_pelanggan }}</option></select></label>
                            <label class="field-wrap full"><span class="field-label">Barang</span><select v-model="modal.data.id_barang" class="field" required><option v-for="item in products" :key="item.id_barang" :value="item.id_barang" :disabled="item.stok < 1">{{ item.nama_barang }} — stok {{ item.stok }} · {{ rupiah(item.harga_satuan) }}</option></select></label>
                            <label class="field-wrap"><span class="field-label">Jumlah beli</span><input v-model.number="modal.data.jumlah_beli" class="field" type="number" min="1" :max="products.find((item) => item.id_barang === modal.data.id_barang)?.stok || 1" required></label>
                            <div class="field-wrap"><span class="field-label">Total sementara</span><div class="field">{{ rupiah((products.find((item) => item.id_barang === modal.data.id_barang)?.harga_satuan || 0) * (modal.data.jumlah_beli || 0) * ((modal.data.jumlah_beli || 0) >= 50 ? .9 : 1)) }}</div></div>
                        </template>
                        <template v-else-if="modal.kind === 'stock'">
                            <div class="field-wrap full"><span class="field-label">Barang</span><div class="field">{{ productName(modal.id) }}</div></div>
                            <label class="field-wrap"><span class="field-label">Operasi</span><select v-model="modal.data.operasi" class="field"><option value="tambah">Tambah stok</option><option value="kurangi">Kurangi stok</option></select></label>
                            <label class="field-wrap"><span class="field-label">Jumlah unit</span><input v-model.number="modal.data.jumlah" class="field" type="number" min="1" required></label>
                        </template>
                    </div>
                    <div class="modal-actions"><button class="button secondary" type="button" @click="closeModal">Batal</button><button class="button" type="submit" :disabled="busy">{{ busy ? 'Menyimpan...' : 'Simpan' }}</button></div>
                </form>
            </section>
        </div>
        <div v-if="notice" class="toast" :class="{ error: noticeIsError }" role="status">{{ notice }}</div>
    </div>
</template>
