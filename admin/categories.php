<?php
/**
 * admin/categories.php — Manage Report Categories
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$message = '';

// Add new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-exclamation-triangle');
    if (empty($name)) {
        $message = 'error:Category name is required.';
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $name, $desc, $icon);
        mysqli_stmt_execute($stmt);
        $message = 'success:Category "' . h($name) . '" added.';
    }
}