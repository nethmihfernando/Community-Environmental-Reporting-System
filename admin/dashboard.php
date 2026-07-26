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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="margin:0;overflow:hidden">
<div class="d-flex" style="height:100vh">

    <!-- ── SIDEBAR ── -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <h5>🌿 EcoReport</h5>
            <div style="color:rgba(255,255,255,.5);font-size:.78rem;margin-top:.3rem">
                Admin Panel
            </div>
        </div>
        <nav class="nav flex-column mt-2">
            <a class="nav-link active" href="dashboard.php">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a class="nav-link" href="manage_reports.php">
                <i class="fas fa-flag"></i> Manage Reports
            </a>
            <a class="nav-link" href="categories.php">
                <i class="fas fa-tags"></i> Categories
            </a>
            <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1rem">
            <a class="nav-link" href="../index.php">
                <i class="fas fa-globe"></i> View Site
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <div class="d-flex flex-column flex-grow-1" style="overflow:hidden">

        <!-- Top bar -->
        <div class="admin-topbar">
            <h6>Dashboard Overview</h6>
            <span class="text-muted small">
                Welcome, <?= h($_SESSION['full_name']) ?> &nbsp;·&nbsp;
                <?= date('l, F j, Y') ?>
            </span>
        </div>

        <div class="admin-content" style="overflow-y:auto">

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="stat-card stat-total">
                        <div class="stat-number"><?= $summary['total'] ?></div>
                        <div class="stat-label">Total Reports</div>
                        <i class="fas fa-database stat-icon"></i>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="stat-card stat-pending">
                        <div class="stat-number"><?= $summary['pending'] ?></div>
                        <div class="stat-label">Pending Review</div>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="stat-card stat-progress">
                        <div class="stat-number"><?= $summary['in_progress'] ?></div>
                        <div class="stat-label">In Progress</div>
                        <i class="fas fa-spinner stat-icon"></i>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="stat-card stat-resolved">
                        <div class="stat-number"><?= $summary['resolved'] ?></div>
                        <div class="stat-label">Resolved</div>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="card-eco h-100">
                        <div class="card-header-eco">
                            <i class="fas fa-chart-bar me-2"></i>Reports by Category
                        </div>
                        <div class="p-4">
                            <canvas id="catChart" height="240"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card-eco h-100">
                        <div class="card-header-eco">
                            <i class="fas fa-chart-pie me-2"></i>Reports by Status
                        </div>
                        <div class="p-4 d-flex justify-content-center">
                            <canvas id="statusChart" style="max-height:240px"></canvas>
                        </div>
                    </div>
                </div>
            </div>
