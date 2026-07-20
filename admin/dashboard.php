<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();   // Only admins can access this page

// ── Gather statistics ────────────────────────────────────────────────────────
$summary = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COUNT(*) AS total,
        SUM(status='pending')     AS pending,
        SUM(status='in_progress') AS in_progress,
        SUM(status='resolved')    AS resolved,
        SUM(status='rejected')    AS rejected
     FROM reports"));

// Reports by category (for bar chart)
$cat_data = mysqli_fetch_all(mysqli_query($conn,
    "SELECT c.name, COUNT(r.report_id) AS cnt
     FROM categories c
     LEFT JOIN reports r ON c.category_id = r.category_id
     GROUP BY c.category_id
     ORDER BY cnt DESC"), MYSQLI_ASSOC);

// Recent 10 reports
$recent = mysqli_fetch_all(mysqli_query($conn,
    "SELECT r.*, c.name AS cat, u.full_name
     FROM reports r
     JOIN categories c ON r.category_id = c.category_id
     JOIN users u ON r.user_id = u.user_id
     ORDER BY r.created_at DESC LIMIT 10"), MYSQLI_ASSOC);

// Total registered users
$user_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS cnt FROM users WHERE role='citizen'"))['cnt'];

// Encode data for Chart.js (PHP array → JSON for JavaScript)
$cat_labels = json_encode(array_column($cat_data, 'name'));
$cat_counts = json_encode(array_column($cat_data, 'cnt'));
?>