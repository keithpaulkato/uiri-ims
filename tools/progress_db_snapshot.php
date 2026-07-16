<?php
$pdo = new PDO('mysql:host=localhost;dbname=uiri_ims;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$tables = array_values($tables);
sort($tables);

echo "DATABASE SNAPSHOT: uiri_ims\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

echo "TABLE COUNTS\n";
foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $count = (int)$pdo->query("SELECT COUNT(*) FROM $quoted")->fetchColumn();
    echo str_pad($table, 28) . " " . $count . "\n";
}

$queries = [
    'Roles' => "SELECT name, description FROM roles ORDER BY id",
    'Branches' => "SELECT name, location FROM branches ORDER BY id",
    'Inventory summary' => "SELECT COUNT(*) AS total_items, COALESCE(SUM(current_stock),0) AS stock_units, COALESCE(SUM(current_stock * unit_price),0) AS stock_value, SUM(current_stock <= minimum_stock) AS low_stock_items, SUM(image IS NOT NULL AND image <> '') AS items_with_images, SUM(serial_number IS NOT NULL AND serial_number <> '') AS items_with_serials FROM inventory_items WHERE is_active = 1",
    'Stock transaction types' => "SELECT transaction_type, COUNT(*) AS count, COALESCE(SUM(quantity),0) AS total_quantity FROM stock_transactions GROUP BY transaction_type ORDER BY transaction_type",
    'Categories by branch' => "SELECT b.name AS branch, COUNT(c.id) AS categories FROM branches b LEFT JOIN categories c ON c.branch_id = b.id GROUP BY b.id, b.name ORDER BY b.id",
    'Users by role' => "SELECT r.name AS role, COUNT(u.id) AS users FROM roles r LEFT JOIN users u ON u.role_id = r.id GROUP BY r.id, r.name ORDER BY r.id",
    'Requests by status' => "SELECT status, COUNT(*) AS count FROM inventory_requests GROUP BY status ORDER BY status",
    'Transfers by status' => "SELECT status, COUNT(*) AS count FROM transfers GROUP BY status ORDER BY status",
    'Maintenance by status' => "SELECT status, COUNT(*) AS count FROM equipment_maintenance GROUP BY status ORDER BY status",
];

foreach ($queries as $title => $sql) {
    echo "\n$title\n";
    try {
        $rows = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        echo "  [skipped: " . $e->getMessage() . "]\n";
        continue;
    }
    if (!$rows) {
        echo "  [no rows]\n";
        continue;
    }
    foreach ($rows as $row) {
        echo "  " . json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
}
