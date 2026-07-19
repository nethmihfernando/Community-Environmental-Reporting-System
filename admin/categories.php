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

$categories = mysqli_fetch_all(mysqli_query($conn,
    "SELECT c.*, COUNT(r.report_id) AS report_count
     FROM categories c LEFT JOIN reports r ON c.category_id = r.category_id
     GROUP BY c.category_id ORDER BY c.name"), MYSQLI_ASSOC);

[$mtype, $mtext] = $message ? explode(':', $message, 2) : ['', ''];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Categories — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="margin:0">