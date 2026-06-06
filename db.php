<?php
// Mengatur Ke Timezone WIB
date_default_timezone_set('Asia/Jakarta');

try {
    $conn = new PDO("sqlite:" . __DIR__ . "/pokemon_store.sqlite");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ambil waktu sekarang dalam format WIB untuk pencatatan log awal
    $current_time = date('Y-m-d H:i:s');

    // Buat tabel products sesuai skema berkas .sql asli kamu
    $conn->exec("CREATE TABLE IF NOT EXISTS products (
        sku INTEGER PRIMARY KEY,
        title TEXT,
        price REAL,
        stock INTEGER,
        category TEXT,
        description TEXT,
        product_url TEXT
    )");

    // Buat tabel orders (Ubah pengisian order_date nanti menggunakan variabel PHP, bukan datetime('now') SQLite)
    $conn->exec("CREATE TABLE IF NOT EXISTS pokemon_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku INTEGER,
        quantity INTEGER,
        order_date TEXT
    )");

    // Buat tabel log konsol untuk visualisasi dashboard mahasiswa
    $conn->exec("CREATE TABLE IF NOT EXISTS db_transaction_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        command TEXT,
        status TEXT,
        timestamp TEXT
    )");

    // Populasi data awal jika database baru saja dibuat/direset
    $check = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($check == 0) {
        $conn->exec("INSERT INTO products (sku, title, price, stock, category, description) VALUES
            (4391, 'Bulbasaur', 63.0, 45, 'Pokemon, Seed', 'Seed Pokemon'),
            (7227, 'Ivysaur', 87.0, 142, 'Pokemon, Seed', 'Bud Pokemon'),
            (7036, 'Venusaur', 105.0, 30, 'Pokemon, Seed', 'Flower Pokemon'),
            (9086, 'Charmander', 48.0, 0, 'Lizard, Pokemon', 'Flame on tail'),
            (1333, 'Pikachu', 37.0, 254, 'Mouse, Pokemon', 'Electric cheeks')");

        // Catat log inisialisasi pertama kali menggunakan waktu WIB ($current_time)
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES
            ('Database initialized. Tables products and pokemon_orders created.', 'RUNNING', '$current_time')");
    }

} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>