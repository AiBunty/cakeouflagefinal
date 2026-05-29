<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=cakeouflage_local;charset=utf8mb4", "cakeouflage", "cakeouflage", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "DB Connected OK\n";

    // Check phone_e164 column
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');
    echo "users columns: " . implode(', ', $colNames) . "\n\n";

    // Find parin email
    $stmt2 = $pdo->query("SELECT id, email, role, phone, phone_e164, is_active FROM users WHERE email LIKE '%parin%' LIMIT 5");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "Parin rows (" . count($rows) . "):\n";
    foreach ($rows as $r) {
        print_r($r);
    }

    // Check role values
    $stmt3 = $pdo->query("SELECT DISTINCT role FROM users LIMIT 10");
    $roles = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "\nDistinct roles in users: ";
    print_r($roles);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
