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

// Delete category
if (!empty($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Prevent deleting if reports use this category
    $count = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM reports WHERE category_id = $id"))['c'];
    if ($count > 0) {
        $message = 'error:Cannot delete — ' . $count . ' report(s) use this category.';
    } else {
        mysqli_query($conn, "DELETE FROM categories WHERE category_id = $id");
        $message = 'success:Category deleted.';
    }
}