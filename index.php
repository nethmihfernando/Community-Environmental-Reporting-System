<?php
/**
 * index.php — Public Homepage
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Fetch 6 most recent reports to display on the homepage
$sql = "SELECT r.*, c.name AS category_name, u.full_name
        FROM reports r
        JOIN categories c ON r.category_id = c.category_id
        JOIN users u ON r.user_id = u.user_id
        ORDER BY r.created_at DESC
        LIMIT 6";
$result      = mysqli_query($conn, $sql);
$recent      = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Count stats for the summary strip
$stats_sql  = "SELECT
    COUNT(*) AS total,
    SUM(status = 'pending')     AS pending,
    SUM(status = 'in_progress') AS in_progress,
    SUM(status = 'resolved')    AS resolved
    FROM reports";
$stats_row  = mysqli_fetch_assoc(mysqli_query($conn, $stats_sql));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — Community Environmental Reporting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-wrap">

<!-- ── NAVIGATION ── -->
<nav class="navbar navbar-expand-lg navbar-eco">
    <div class="container">
        <a class="navbar-brand" href="index.php">🌿 <span>Eco</span>Report</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <i class="fas fa-bars text-white"></i>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                <?php if (is_logged_in()): ?>
                    <?php if (is_admin()): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="user/dashboard.php">My Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="user/submit_report.php">Submit Report</a></li>
                        <li class="nav-item"><a class="nav-link" href="user/my_reports.php">My Reports</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link btn-nav-cta ms-1" href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="hero-title">Report &amp; <span class="accent">Protect</span><br>Our Environment</h1>
                <p class="hero-subtitle">
                    Spotted illegal dumping, pollution, or an environmental hazard?
                    Report it in seconds — tag the location on a map and we'll make sure the right authorities act.
                </p>
                <div class="mt-4 d-flex gap-3 flex-wrap">
                    <?php if (!is_logged_in()): ?>
                        <a href="register.php" class="btn btn-eco-primary btn-lg">Get Started Free</a>
                        <a href="login.php"    class="btn btn-eco-outline btn-lg text-white border-white">Login</a>
                    <?php else: ?>
                        <a href="user/submit_report.php" class="btn btn-eco-primary btn-lg">
                            <i class="fas fa-plus me-2"></i>Submit a Report
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 mt-4 mt-lg-0">
                <!-- Quick stats strip -->
                <div class="row g-3">
                    <div class="col-6"><div class="stat-card stat-total">
                        <div class="stat-number"><?= $stats_row['total'] ?></div>
                        <div class="stat-label">Total Reports</div>
                        <i class="fas fa-flag stat-icon"></i>
                    </div></div>
                    <div class="col-6"><div class="stat-card stat-pending">
                        <div class="stat-number"><?= $stats_row['pending'] ?></div>
                        <div class="stat-label">Pending Review</div>
                        <i class="fas fa-clock stat-icon"></i>
                    </div></div>
                    <div class="col-6"><div class="stat-card stat-progress">
                        <div class="stat-number"><?= $stats_row['in_progress'] ?></div>
                        <div class="stat-label">In Progress</div>
                        <i class="fas fa-spinner stat-icon"></i>
                    </div></div>
                    <div class="col-6"><div class="stat-card stat-resolved">
                        <div class="stat-number"><?= $stats_row['resolved'] ?></div>
                        <div class="stat-label">Resolved</div>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── RECENT REPORTS ── -->
<main class="main-wrap">
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="section-title">Recent Reports</h2>
                <p class="text-muted mt-3">Latest environmental issues submitted by the community</p>
            </div>
            <?php if (is_logged_in()): ?>
                <a href="user/submit_report.php" class="btn btn-eco-primary">
                    <i class="fas fa-plus me-1"></i> New Report
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($recent)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-leaf fa-3x mb-3 text-eco"></i>
                <p>No reports yet. Be the first to report an issue!</p>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($recent as $r): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-eco report-card h-100">
                    <?php if ($r['image_path'] && file_exists(UPLOAD_DIR . $r['image_path'])): ?>
                        <img src="<?= UPLOAD_URL . h($r['image_path']) ?>"
                             class="report-img" alt="Report image">
                    <?php else: ?>
                        <div class="no-img"><i class="fas fa-camera"></i></div>
                    <?php endif; ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-secondary small"><?= h($r['category_name']) ?></span>
                            <?= status_badge($r['status']) ?>
                        </div>
                        <h6 class="mb-1"><?= h($r['title']) ?></h6>
                        <p class="text-muted small mb-2"><?= h(mb_substr($r['description'], 0, 90)) ?>…</p>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span><i class="fas fa-user me-1"></i><?= h($r['full_name']) ?></span>
                            <span><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="py-5 bg-eco">
    <div class="container text-center">
        <h2 class="section-title mb-5 d-inline-block">How It Works</h2>
        <div class="row g-4 mt-2">
            <?php foreach ([
                ['fas fa-user-plus','Register','Create a free account in under a minute.'],
                ['fas fa-map-marker-alt','Pin the Location','Click on the map to mark exactly where the issue is.'],
                ['fas fa-camera','Upload Evidence','Attach a photo to support your report.'],
                ['fas fa-shield-alt','Authorities Act','Admins review and dispatch the relevant teams.'],
            ] as $i => [$icon, $title, $desc]): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card-eco p-4 h-100 text-start">
                    <div class="mb-3">
                        <span class="d-inline-flex align-items-center justify-content-center
                              rounded-circle bg-eco text-eco fw-bold"
                              style="width:44px;height:44px;font-size:1.1rem">
                            <?= $i+1 ?>
                        </span>
                    </div>
                    <i class="fas <?= $icon ?> text-eco fa-lg mb-2"></i>
                    <h6 class="fw-700 mb-1"><?= $title ?></h6>
                    <p class="text-muted small mb-0"><?= $desc ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
</main>

<!-- ── FOOTER ── -->
<footer>
    <p class="mb-0">© <?= date('Y') ?> <?= SITE_NAME ?> — Built for communities, powered by citizens.
        <?php if (!is_logged_in()): ?>
            <a href="admin/login.php">Admin Login Portal</a>
        <?php endif; ?>
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
