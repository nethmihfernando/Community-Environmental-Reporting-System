<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$user_id = $_SESSION['user_id'];
$message = '';

// ── Handle delete ────────────────────────────────────────────────────────────
// Citizens can only delete their OWN reports
if (!empty($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];

    // First fetch the report — make sure it belongs to this user
    $chk = mysqli_prepare($conn,
        "SELECT image_path FROM reports WHERE report_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'ii', $del_id, $user_id);
    mysqli_stmt_execute($chk);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);

    if ($row) {
        // Delete the uploaded photo file from disk if it exists
        if ($row['image_path'] && file_exists(UPLOAD_DIR . $row['image_path'])) {
            unlink(UPLOAD_DIR . $row['image_path']);
        }

        // Delete the report row (status_logs are deleted automatically via CASCADE)
        $del = mysqli_prepare($conn, "DELETE FROM reports WHERE report_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($del, 'ii', $del_id, $user_id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        $message = 'success:Report deleted successfully.';
    } else {
        $message = 'error:Report not found or you do not have permission to delete it.';
    }
}

// ── Fetch all reports by this user ───────────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT r.*, c.name AS category_name
     FROM reports r
     JOIN categories c ON r.category_id = c.category_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$reports = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ── Load selected report detail ───────────────────────────────────────────────
$selected = null;
$logs     = [];
if (!empty($_GET['id'])) {
    $rid = (int)$_GET['id'];
    $s2  = mysqli_prepare($conn,
        "SELECT r.*, c.name AS category_name
         FROM reports r
         JOIN categories c ON r.category_id = c.category_id
         WHERE r.report_id = ? AND r.user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($s2, 'ii', $rid, $user_id);
    mysqli_stmt_execute($s2);
    $selected = mysqli_fetch_assoc(mysqli_stmt_get_result($s2));
    mysqli_stmt_close($s2);

    if ($selected) {
        $s3 = mysqli_prepare($conn,
            "SELECT sl.*, u.full_name
             FROM status_logs sl
             JOIN users u ON sl.changed_by = u.user_id
             WHERE sl.report_id = ?
             ORDER BY sl.changed_at ASC");
        mysqli_stmt_bind_param($s3, 'i', $rid);
        mysqli_stmt_execute($s3);
        $logs = mysqli_fetch_all(mysqli_stmt_get_result($s3), MYSQLI_ASSOC);
        mysqli_stmt_close($s3);
    }
}

[$msg_type, $msg_text] = $message ? explode(':', $message, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Reports — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-card-item {
            border-left: 4px solid var(--border);
            transition: var(--transition);
            cursor: pointer;
        }
        .report-card-item:hover  { border-left-color: var(--green-light); }
        .report-card-item.active { border-left-color: var(--green-mid); background: var(--green-pale); }

        /* Delete button sits top-right of each card */
        .card-actions { position: absolute; top: .6rem; right: .6rem; }

        /* Status timeline */
        .status-timeline { position: relative; padding-left: 1.4rem; }
        .status-timeline::before { content:''; position:absolute; left:.4rem; top:0; bottom:0; width:2px; background:var(--border); }
        .tl-item  { position: relative; margin-bottom: 1.1rem; }
        .tl-dot   { position:absolute; left:-1.4rem; width:13px; height:13px; border-radius:50%; background:var(--green-mid); border:2px solid #fff; box-shadow:0 0 0 2px var(--green-mid); top:4px; }
    </style>
</head>
<body class="page-wrap">

<!-- ── NAVBAR ── -->
<nav class="navbar navbar-expand-lg navbar-eco">
    <div class="container">
        <a class="navbar-brand" href="../index.php">🌿 <span>Eco</span>Report</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="dashboard.php"   class="nav-link text-white"><i class="fas fa-home me-1"></i>Dashboard</a>
            <a href="submit_report.php" class="nav-link text-white"><i class="fas fa-plus me-1"></i>New Report</a>
            <a href="../logout.php"   class="nav-link text-white"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<main class="main-wrap py-5">
<div class="container">

    <!-- Flash message -->
    <?php if ($msg_text): ?>
    <div class="alert alert-eco alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> mb-4 auto-dismiss">
        <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
        <?= h($msg_text) ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── LEFT: Reports list ── -->
        <div class="col-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0">My Reports</h2>
                <span class="badge bg-secondary"><?= count($reports) ?> total</span>
            </div>

            <?php if (empty($reports)): ?>
                <div class="card-eco p-4 text-center text-muted">
                    <i class="fas fa-leaf fa-2x mb-3 text-eco"></i>
                    <p class="mb-3">You haven't submitted any reports yet.</p>
                    <a href="submit_report.php" class="btn btn-eco-primary">
                        <i class="fas fa-plus me-1"></i>Submit Your First Report
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                <?php foreach ($reports as $r): ?>
                    <div class="card-eco report-card-item p-3 position-relative
                                <?= (isset($_GET['id']) && $_GET['id'] == $r['report_id']) ? 'active' : '' ?>">

                        <!-- Clicking the card body opens the detail panel -->
                        <a href="?id=<?= $r['report_id'] ?>" class="text-decoration-none stretched-link"></a>



                        <div class="d-flex justify-content-between align-items-start mb-1 pe-5">
                            <span class="badge bg-secondary small"><?= h($r['category_name']) ?></span>
                            <?= status_badge($r['status']) ?>
                        </div>
                        <h6 class="mb-1" style="color:var(--navy)"><?= h($r['title']) ?></h6>
                        <div class="small text-muted">
                            <i class="fas fa-calendar me-1"></i><?= date('M j, Y', strtotime($r['created_at'])) ?>
                            <?php if ($r['address']): ?>
                                &nbsp;·&nbsp;<i class="fas fa-map-marker-alt me-1"></i>
                                <?= h(mb_substr($r['address'], 0, 35)) ?>…
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── RIGHT: Report detail ── -->
        <div class="col-lg-7">
            <?php if ($selected): ?>
            <div class="card-eco">
                <div class="card-header-eco d-flex justify-content-between align-items-center">
                    <span class="text-truncate me-2"><?= h($selected['title']) ?></span>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <?= status_badge($selected['status']) ?>
                        <!-- Delete button in detail panel too -->
                        <a href="?delete=<?= $selected['report_id'] ?>"
                           class="btn btn-sm btn-outline-danger py-0 px-2"
                           style="font-size:.72rem;color:#fff;border-color:rgba(255,255,255,.4)"
                           onclick="return confirm('Delete this report? This cannot be undone.')">
                            <i class="fas fa-trash-alt me-1"></i>Delete
                        </a>
                    </div>
                </div>

                <div class="p-4">
                    <!-- Photo -->
                    <?php if ($selected['image_path'] && file_exists(UPLOAD_DIR . $selected['image_path'])): ?>
                        <img src="<?= UPLOAD_URL . h($selected['image_path']) ?>"
                             class="w-100 rounded mb-3"
                             style="max-height:240px;object-fit:cover"
                             alt="Report photo">
                    <?php endif; ?>

                    <!-- Meta info -->
                    <div class="row g-2 mb-3 small">
                        <div class="col-6">
                            <span class="text-muted">Category:</span>
                            <strong><?= h($selected['category_name']) ?></strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Priority:</span>
                            <?= priority_badge($selected['priority']) ?>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Submitted:</span>
                            <strong><?= date('M j, Y', strtotime($selected['created_at'])) ?></strong>
                        </div>
                        <?php if ($selected['address']): ?>
                        <div class="col-12">
                            <span class="text-muted"><i class="fas fa-map-marker-alt me-1 text-eco"></i></span>
                            <?= h($selected['address']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <p class="mb-3" style="white-space:pre-wrap"><?= nl2br(h($selected['description'])) ?></p>

                    <!-- Admin notes -->
                    <?php if ($selected['admin_notes']): ?>
                    <div class="alert alert-eco alert-info">
                        <strong><i class="fas fa-comment me-1"></i>Message from Authorities:</strong><br>
                        <?= nl2br(h($selected['admin_notes'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Status Timeline -->
                    <?php if ($logs): ?>
                    <h6 class="mt-4 mb-3">
                        <i class="fas fa-history me-2 text-eco"></i>Status History
                    </h6>
                    <div class="status-timeline">
                        <?php foreach ($logs as $log): ?>
                        <div class="tl-item">
                            <div class="tl-dot"></div>
                            <?= status_badge($log['new_status']) ?>
                            <div class="small text-muted mt-1">
                                by <strong><?= h($log['full_name']) ?></strong>
                                · <?= date('M j, Y g:i a', strtotime($log['changed_at'])) ?>
                            </div>
                            <?php if ($log['note']): ?>
                                <div class="small mt-1 fst-italic"><?= h($log['note']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Bottom action buttons -->
                    <div class="d-flex gap-2 mt-4 pt-3 border-top">
                        <a href="../index.php" class="btn btn-eco-outline">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                        <a href="submit_report.php" class="btn btn-eco-primary">
                            <i class="fas fa-plus me-1"></i>New Report
                        </a>
                        <a href="?delete=<?= $selected['report_id'] ?>"
                           class="btn btn-outline-danger ms-auto"
                           onclick="return confirm('Delete this report? This cannot be undone.')">
                            <i class="fas fa-trash-alt me-1"></i>Delete Report
                        </a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Empty state — no report selected yet -->
            <div class="card-eco p-5 text-center text-muted"
                 style="min-height:300px;display:flex;align-items:center;justify-content:center">
                <div>
                    <i class="fas fa-hand-pointer fa-3x mb-3 text-eco"></i>
                    <p class="mb-3">Select a report on the left to see its full details and status history.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="../index.php" class="btn btn-eco-outline">
                            <i class="fas fa-home me-1"></i>Go Home
                        </a>
                        <a href="submit_report.php" class="btn btn-eco-primary">
                            <i class="fas fa-plus me-1"></i>New Report
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</main>

<footer><p class="mb-0">© <?= date('Y') ?> <?= SITE_NAME ?></p></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss flash messages after 4 seconds
setTimeout(() => {
    document.querySelectorAll('.auto-dismiss').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>
</body>
</html>