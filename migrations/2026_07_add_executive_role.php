<?php
// Migration: add Executive role
require_once __DIR__ . '/../includes/config.php';

$pdo = db();
try {
    $name = 'Executive';
    $desc = 'Executive users with report-level admin access';
    $check = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $check->execute([$name]);
    if ($check->fetch()) {
        echo "Role '$name' already exists.\n";
        exit(0);
    }
    $ins = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
    $ins->execute([$name, $desc]);
    echo "Added role: $name\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
