<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$report_id = (int)($_GET['id'] ?? 0);
if ($report_id <= 0) redirect(SITE_URL . '/index.php');

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

$sql = "SELECT r.*, c.name AS category_name, u.full_name AS reporter_name
        FROM reports r
        JOIN categories c ON r.category_id = c.category_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.report_id = ?";
if ($role !== 'admin') {
    $sql .= " AND r.user_id = ?";
}

$stmt = mysqli_prepare($conn, $sql);
if ($role !== 'admin') {
    mysqli_stmt_bind_param($stmt, 'ii', $report_id, $user_id);
} else {
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
}
mysqli_stmt_execute($stmt);
$report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$report) redirect(SITE_URL . '/index.php?msg=Report+not+found');

// Increment view count
mysqli_query($conn, "UPDATE reports SET views = views + 1 WHERE report_id = $report_id");

// Status history
$log_stmt = mysqli_prepare($conn,
    "SELECT sl.*, u.full_name AS changed_by_name
     FROM status_logs sl
     JOIN users u ON sl.changed_by = u.user_id
     WHERE sl.report_id = ?
     ORDER BY sl.changed_at ASC");
mysqli_stmt_bind_param($log_stmt, 'i', $report_id);
mysqli_stmt_execute($log_stmt);
$logs = mysqli_fetch_all(mysqli_stmt_get_result($log_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($log_stmt);

$has_location = !empty($report['latitude']) && !empty($report['longitude']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($report['title']) ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if ($has_location): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #mini-map { height: 220px; border-radius: var(--radius-sm); }
        .status-timeline { position: relative; padding-left: 1.5rem; }
        .status-timeline::before { content:''; position:absolute; left:.45rem; top:0; bottom:0; width:2px; background:var(--border); }
        .tl-item { position: relative; margin-bottom: 1.2rem; }
        .tl-dot  { position:absolute; left:-1.5rem; width:14px; height:14px; border-radius:50%; background:var(--green-mid); border:2px solid #fff; box-shadow:0 0 0 2px var(--green-mid); top:4px; }
    </style>
</head>
<body class="page-wrap">

<nav class="navbar navbar-expand-lg navbar-eco">
    <div class="container">
        <a class="navbar-brand" href="../index.php">🌿 <span>Eco</span>Report</a>
        <div class="ms-auto d-flex gap-2">
            <a href="dashboard.php" class="nav-link text-white">
                <i class="fas fa-home me-1"></i>Dashboard
            </a>
            <a href="my_reports.php" class="nav-link text-white">
                <i class="fas fa-arrow-left me-1"></i>My Reports
            </a>
            <a href="../logout.php" class="nav-link text-white">Logout</a>
        </div>
    </div>
</nav>

<main class="main-wrap py-5">
<div class="container" style="max-width:960px">

    <nav class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="my_reports.php">My Reports</a></li>
            <li class="breadcrumb-item active"><?= h(mb_substr($report['title'], 0, 45)) ?>…</li>
        </ol>
    </nav>

    <div class="row g-4">

        <!-- Left: Main content -->
        <div class="col-lg-8">
            <div class="card-eco">
                <div class="p-4 pb-0">
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <?= status_badge($report['status']) ?>
                        <?= priority_badge($report['priority']) ?>
                    </div>
                    <h2 class="mb-1"><?= h($report['title']) ?></h2>
                    <div class="text-muted small mb-3">
                        <i class="fas fa-tag me-1"></i><?= h($report['category_name']) ?>
                        &nbsp;·&nbsp;
                        <i class="fas fa-calendar me-1"></i><?= date('F j, Y', strtotime($report['created_at'])) ?>
                        &nbsp;·&nbsp;
                        <i class="fas fa-eye me-1"></i><?= $report['views'] ?> views
                    </div>
                </div>

                <?php if ($report['image_path'] && file_exists(UPLOAD_DIR . $report['image_path'])): ?>
                <img src="<?= UPLOAD_URL . h($report['image_path']) ?>"
                     alt="Report photo"
                     style="width:100%;max-height:380px;object-fit:cover">
                <?php endif; ?>

                <div class="p-4">
                    <h6 class="section-title mb-4">Description</h6>
                    <p style="white-space:pre-wrap;line-height:1.75"><?= h($report['description']) ?></p>

                    <?php if ($report['admin_notes']): ?>
                    <div class="alert alert-eco alert-info mt-4">
                        <h6 class="mb-1"><i class="fas fa-comment-dots me-2"></i>Message from Authorities</h6>
                        <p class="mb-0"><?= nl2br(h($report['admin_notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Location + Timeline -->
        <div class="col-lg-4">

            <!-- Location card -->
            <div class="card-eco mb-4">
                <div class="card-header-eco">
                    <i class="fas fa-map-marker-alt me-2"></i>Location
                </div>
                <div class="p-3">
                    <?php if ($report['address']): ?>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-location-dot me-1"></i><?= h($report['address']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($has_location): ?>
                        <!-- Leaflet mini-map -->
                        <div id="mini-map"></div>
                        <p class="text-muted mt-2 mb-0" style="font-size:.75rem;font-family:monospace">
                            <?= number_format((float)$report['latitude'], 6) ?>,
                            <?= number_format((float)$report['longitude'], 6) ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted small">No location was pinned for this report.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="card-eco">
                <div class="card-header-eco">
                    <i class="fas fa-history me-2"></i>Status Timeline
                </div>
                <div class="p-4">
                    <?php if (empty($logs)): ?>
                        <p class="text-muted small mb-0">No status changes yet — report is being reviewed.</p>
                    <?php else: ?>
                    <div class="status-timeline">
                        <?php foreach ($logs as $log): ?>
                        <div class="tl-item">
                            <div class="tl-dot"></div>
                            <?= status_badge($log['new_status']) ?>
                            <div class="small text-muted mt-1">
                                by <strong><?= h($log['changed_by_name']) ?></strong><br>
                                <?= date('M j, Y g:i a', strtotime($log['changed_at'])) ?>
                            </div>
                            <?php if ($log['note']): ?>
                                <div class="small mt-1 fst-italic text-muted"><?= h($log['note']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
</main>

<footer><p class="mb-0">© <?= date('Y') ?> <?= SITE_NAME ?></p></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($has_location): ?>
<!-- Leaflet JS for the mini-map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Read the saved coordinates from PHP
    const lat = <?= (float)$report['latitude'] ?>;
    const lng = <?= (float)$report['longitude'] ?>;

    // Create a small non-interactive map centred on the report location
    const miniMap = L.map('mini-map', {
        center:          [lat, lng],
        zoom:            15,
        zoomControl:     true,
        scrollWheelZoom: false,   // don't zoom when user scrolls the page
        dragging:        false    // static view — no dragging needed here
    });

    // Free OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(miniMap);

    // Drop a pin at the report location
    L.marker([lat, lng]).addTo(miniMap)
        .bindPopup('<?= addslashes(h($report['title'])) ?>')
        .openPopup();
</script>
<?php endif; ?>
</body>
</html>
