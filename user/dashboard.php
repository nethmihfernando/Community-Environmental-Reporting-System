<?php
/**
 * user/dashboard.php — Citizen Dashboard Overview
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();   // Regular citizens must be logged in

if (is_admin()) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

$user_id = $_SESSION['user_id'];

// Gather statistics for this specific user
$summary_result = mysqli_query($conn,
    "SELECT
        COUNT(*) AS total,
        SUM(status='pending')     AS pending,
        SUM(status='in_progress') AS in_progress,
        SUM(status='resolved')    AS resolved,
        SUM(status='rejected')    AS rejected
     FROM reports
     WHERE user_id = $user_id");
$summary = mysqli_fetch_assoc($summary_result);

// Set null values to 0
$summary['total']       = (int)($summary['total'] ?? 0);
$summary['pending']     = (int)($summary['pending'] ?? 0);
$summary['in_progress'] = (int)($summary['in_progress'] ?? 0);
$summary['resolved']    = (int)($summary['resolved'] ?? 0);
$summary['rejected']    = (int)($summary['rejected'] ?? 0);

// Fetch recent 5 reports by this user
$stmt = mysqli_prepare($conn,
    "SELECT r.*, c.name AS cat
     FROM reports r
     JOIN categories c ON r.category_id = c.category_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC LIMIT 5");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$recent = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Citizen Dashboard — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="margin:0;overflow:hidden">
<div class="d-flex" style="height:100vh">

    <!-- ── SIDEBAR ── -->
    <aside class="admin-sidebar" style="background:var(--green-dark); border-right: 3px solid var(--green-mid)">
        <div class="sidebar-brand" style="border-bottom:1px solid rgba(255,255,255,.15)">
            <h5 style="color:#fff">🌿 EcoReport</h5>
            <div style="color:rgba(255,255,255,.75);font-size:.78rem;margin-top:.3rem">
                Citizen Portal
            </div>
        </div>
        <nav class="nav flex-column mt-2">
            <a class="nav-link active" href="dashboard.php" style="color:#fff; background:rgba(255,255,255,.1)">
                <i class="fas fa-home"></i> Dashboard Home
            </a>
            <a class="nav-link text-white-50" href="submit_report.php">
                <i class="fas fa-plus-circle"></i> Submit Report
            </a>
            <a class="nav-link text-white-50" href="my_reports.php">
                <i class="fas fa-list-alt"></i> My Reports
            </a>
            <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1rem">
            <a class="nav-link text-white-50" href="../index.php">
                <i class="fas fa-globe"></i> View Homepage
            </a>
            <a class="nav-link text-white-50" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <div class="d-flex flex-column flex-grow-1" style="overflow:hidden">

        <!-- Top bar -->
        <div class="admin-topbar">
            <h6>Citizen Dashboard Overview</h6>
            <span class="text-muted small">
                Welcome back, <?= h($_SESSION['full_name']) ?> &nbsp;·&nbsp;
                <?= date('l, F j, Y') ?>
            </span>
        </div>

        <div class="admin-content" style="overflow-y:auto">

            <!-- Welcome Header Alert -->
            <div class="alert alert-eco alert-success mb-4 p-4" style="background: var(--green-pale); border-color: var(--green-mid)">
                <h5 class="alert-heading fw-bold mb-1"><i class="fas fa-leaf me-2 text-eco"></i>Thank you for keeping our community clean!</h5>
                <p class="mb-0 text-muted small">From this portal, you can submit environmental issues, track current statuses, and review action histories. Every report matters.</p>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="stat-card stat-total">
                        <div class="stat-number"><?= $summary['total'] ?></div>
                        <div class="stat-label">My Submissions</div>
                        <i class="fas fa-folder stat-icon"></i>
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
                        <div class="stat-label">Resolved Issues</div>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Main section row -->
            <div class="row g-4 mb-4">
                <!-- Doughnut status chart -->
                <div class="col-lg-5">
                    <div class="card-eco h-100">
                        <div class="card-header-eco" style="background:var(--green-mid)">
                            <i class="fas fa-chart-pie me-2"></i>Report Status Breakdown
                        </div>
                        <div class="p-4 d-flex justify-content-center align-items-center">
                            <?php if ($summary['total'] > 0): ?>
                                <canvas id="statusChart" style="max-height:220px"></canvas>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="fas fa-chart-bar fa-2x mb-2 text-eco"></i>
                                    <p class="mb-0">No reports submitted to show chart.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent User Reports -->
                <div class="col-lg-7">
                    <div class="card-eco h-100">
                        <div class="card-header-eco" style="background:var(--green-mid)">
                            <i class="fas fa-list me-2"></i>Recent Submissions
                        </div>
                        <div class="p-0">
                            <?php if (empty($recent)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-flag fa-2x mb-2"></i>
                                    <p class="small mb-3">You haven't submitted any reports yet.</p>
                                    <a href="submit_report.php" class="btn btn-sm btn-eco-primary">Submit Report</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-eco mb-0">
                                        <thead>
                                            <tr>
                                                <th>Title</th><th>Category</th><th>Status</th><th>Submitted</th><th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent as $r): ?>
                                            <tr>
                                                <td><strong><?= h(mb_substr($r['title'], 0, 30)) ?>...</strong></td>
                                                <td><span class="badge bg-secondary"><?= h($r['cat']) ?></span></td>
                                                <td><?= status_badge($r['status']) ?></td>
                                                <td class="text-muted small"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                                <td>
                                                    <a href="my_reports.php?id=<?= $r['report_id'] ?>"
                                                       class="btn btn-sm btn-eco-outline py-0 px-2">View</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /admin-content -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($summary['total'] > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels:   ['Pending', 'In Progress', 'Resolved', 'Rejected'],
        datasets: [{
            data: [
                <?= $summary['pending'] ?>,
                <?= $summary['in_progress'] ?>,
                <?= $summary['resolved'] ?>,
                <?= $summary['rejected'] ?>
            ],
            backgroundColor: ['#f39c12','#3498db','#2d7a4f','#e74c3c'],
            borderWidth:     2,
            borderColor:     '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } }
        },
        cutout: '60%'
    }
});
</script>
<?php endif; ?>
</body>
</html>
