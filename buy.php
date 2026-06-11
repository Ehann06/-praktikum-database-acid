<?php

// Mengatur Timezone
date_default_timezone_set('Asia/Jakarta');

include 'db.php';

$sku  = $_POST['sku'];
$mode = $_POST['mode'];

$time = date('Y-m-d H:i:s');

if ($mode === 'standard') {

    $conn->exec("
        INSERT INTO db_transaction_logs
        (command, status, timestamp)
        VALUES
        ('START TRANSACTION;', 'BEGIN', '$time')
    ");

    $conn->beginTransaction();

    try {

        $stmt = $conn->prepare("
            SELECT title, stock
            FROM products
            WHERE sku = :sku
        ");

        $stmt->execute([
            'sku' => $sku
        ]);

        $product = $stmt->fetch();

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            ('SELECT stock FROM products WHERE sku=$sku;', 'RUNNING', '$time')
        ");

        if (!$product || $product['stock'] <= 0) {

            $name = $product ? $product['title'] : 'Unknown';

            throw new Exception(
                "Stok {$name} habis! Gagal memenuhi aturan bisnis."
            );
        }

        $conn->prepare("
            UPDATE products
            SET stock = stock - 1
            WHERE sku = :sku
        ")->execute([
            'sku' => $sku
        ]);

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            ('UPDATE products SET stock = stock - 1;', 'RUNNING', '$time')
        ");

        $stmtOrder = $conn->prepare("
            INSERT INTO pokemon_orders
            (sku, quantity, order_date)
            VALUES
            (:sku, 1, :order_date)
        ");

        $stmtOrder->execute([
            'sku' => $sku,
            'order_date' => $time
        ]);

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            ('INSERT INTO pokemon_orders VALUES(...);', 'RUNNING', '$time')
        ");

        $conn->commit();

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            (
                'COMMIT; Transaksi Berhasil diselesaikan.',
                'COMMIT',
                '$time'
            )
        ");

        header("Location: index.php");
        exit();

    } catch (Exception $e) {

        $conn->rollBack();

        $errMessage = $e->getMessage();

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            (
                'ROLLBACK; Alasan: $errMessage',
                'ROLLBACK',
                '$time'
            )
        ");

        header("Location: index.php");
        exit();
    }

} else if ($mode === 'savepoint_demo') {

    $conn->exec("
        INSERT INTO db_transaction_logs
        (command, status, timestamp)
        VALUES
        ('START TRANSACTION;', 'BEGIN', '$time')
    ");

    $conn->beginTransaction();

    try {

        $conn->prepare("
            UPDATE products
            SET stock = stock - 1
            WHERE sku = :sku
        ")->execute([
            'sku' => $sku
        ]);

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            ('UPDATE stock (Langkah Utama);', 'RUNNING', '$time')
        ");

        $conn->exec("SAVEPOINT titik_aman_stok");

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            ('SAVEPOINT titik_aman_stok;', 'SAVEPOINT', '$time')
        ");

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            (
                'Mencoba INSERT order... [Sengaja Digagalkan]',
                'FAILED',
                '$time'
            )
        ");

        $conn->exec("
            ROLLBACK TO SAVEPOINT titik_aman_stok
        ");

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            (
                'ROLLBACK TO SAVEPOINT titik_aman_stok; (Stok tetap berkurang, baris order dibatalkan)',
                'PARTIAL_ROLLBACK',
                '$time'
            )
        ");

        $conn->commit();

        $conn->exec("
            INSERT INTO db_transaction_logs
            (command, status, timestamp)
            VALUES
            (
                'COMMIT; Selesai dengan parsial rollback.',
                'COMMIT',
                '$time'
            )
        ");

        header("Location: index.php");
        exit();

    } catch(Exception $e) {

        $conn->rollBack();

        header("Location: index.php");
        exit();
    }
}

?>