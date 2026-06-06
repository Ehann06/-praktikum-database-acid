<?php
// Pastikan variabel $mode, $sku, dan $time sudah terdefinisi di bagian atas file sebelum blok ini

if ($mode === 'standard') {
    // 1. Catat log inisialisasi transaksi ke database terlebih dahulu
    $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('START TRANSACTION;', 'BEGIN', '$time')");
    
    // 2. Mulai transaksi PDO
    $conn->beginTransaction();
    
    try {
        // Ambil data produk
        $stmt = $conn->prepare("SELECT title, stock FROM products WHERE sku = :sku");
        $stmt->execute(['sku' => $sku]);
        $product = $stmt->fetch();
        
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('SELECT stock FROM products WHERE sku=$sku;', 'RUNNING', '$time')");
        
        // Deteksi Kondisi Kegagalan (Prinsip Consistency dan Atomicity)
        if (!$product || $product['stock'] <= 0) {
            $name = $product ? $product['title'] : 'Unknown';
            throw new Exception("Stok {$name} habis! Gagal memenuhi aturan bisnis.");
        }
        
        // Jalankan Update Stok
        $conn->prepare("UPDATE products SET stock = stock - 1 WHERE sku = :sku")->execute(['sku' => $sku]);
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('UPDATE products SET stock = stock - 1;', 'RUNNING', '$time')");
        
        // Fix Sinkronisasi Waktu & Input Order
        $stmtOrder = $conn->prepare("INSERT INTO pokemon_orders (sku, quantity, order_date) VALUES (:sku, 1, :order_date)");
        $stmtOrder->execute([
            'sku'        => $sku,
            'order_date' => $time
        ]);
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('INSERT INTO pokemon_orders VALUES(...);', 'RUNNING', '$time')");
        
        // CATATAN PENTING: Tulis log sukses SEBELUM commit agar log ini ikut terkunci secara permanen di dalam database (Durability)
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('COMMIT; Transaksi Berhasil diselesaikan.', 'COMMIT', '$time')");
        
        // Kunci perubahan selamanya
        $conn->commit();
        
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $errMessage = $e->getMessage();
        
        // Batalkan seluruh transaksi database
        $conn->rollBack();
        
        // Tulis log kegagalan setelah rollback selesai
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('ROLLBACK; Alasan: $errMessage', 'ROLLBACK', '$time')");
        
        header("Location: index.php");
        exit();
    }

} else if ($mode === 'savepoint_demo') {
    // Savepoint Simulation
    $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('START TRANSACTION;', 'BEGIN', '$time')");
    $conn->beginTransaction();
    
    try {
        // Kurangi stok utama
        $conn->prepare("UPDATE products SET stock = stock - 1 WHERE sku = :sku")->execute(['sku' => $sku]);
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('UPDATE stock (Langkah Utama);', 'RUNNING', '$time')");
        
        // Membuat Titik Simpan Sementara (SAVEPOINT) via sintaks manual SQLite
        $conn->exec("SAVEPOINT titik_aman_stok");
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('SAVEPOINT titik_aman_stok;', 'SAVEPOINT', '$time')");
        
        // Memicu error palsu pada baris pencatatan order
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('Mencoba INSERT order... [Sengaja Digagalkan]', 'FAILED', '$time')");
        
        // Melakukan Rollback khusus ke SAVEPOINT, bukan ke awal transaksi awal
        $conn->exec("ROLLBACK TO SAVEPOINT titik_aman_stok");
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('ROLLBACK TO SAVEPOINT titik_aman_stok; (Stok tetap berkurang, baris order dibatalkan)', 'PARTIAL_ROLLBACK', '$time')");
        
        // Tulis log komit parsial SEBELUM perintah commit dijalankan
        $conn->exec("INSERT INTO db_transaction_logs (command, status, timestamp) VALUES ('COMMIT; Selesai dengan parsial rollback.', 'COMMIT', '$time')");
        
        // Commit sisa transaksi yang valid
        $conn->commit();
        
        header("Location: index.php");
        exit();
        
    } catch(Exception $e) {
        $conn->rollBack();
        header("Location: index.php");
        exit();
    }
}
?>