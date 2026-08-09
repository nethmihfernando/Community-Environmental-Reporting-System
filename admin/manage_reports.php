<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$message = '';

// ── Handle status update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_status'])) {
    $report_id  = (int)$_POST['report_id'];
    $new_status = $_POST['new_status']  ?? '';
    $note       = trim($_POST['note']   ?? '');
    $admin_note = trim($_POST['admin_notes'] ?? '');
    $valid_statuses = ['pending','in_progress','resolved','rejected'];

    if (!in_array($new_status, $valid_statuses)) {
        $message = 'error:Invalid status.';
    } else {
        // Get the current status (we'll log the change)
        $cur = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT status FROM reports WHERE report_id = $report_id LIMIT 1"));

        if ($cur) {
            // Update the report
            $stmt = mysqli_prepare($conn,
                "UPDATE reports SET status=?, admin_notes=?, updated_at=NOW()
                 WHERE report_id=?");
            mysqli_stmt_bind_param($stmt, 'ssi', $new_status, $admin_note, $report_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Log the change in status_logs
            $admin_id = $_SESSION['user_id'];
            $stmt2 = mysqli_prepare($conn,
                "INSERT INTO status_logs (report_id, changed_by, old_status, new_status, note)
                 VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, 'iisss',
                $report_id, $admin_id, $cur['status'], $new_status, $note);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            $message = 'success:Report #' . $report_id . ' updated to ' . ucwords(str_replace('_',' ',$new_status)) . '.';
        }
    }
}

// ── Handle delete ────────────────────────────────────────────────────────────
if (!empty($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // First get image path so we can delete the file too
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT image_path FROM reports WHERE report_id = $del_id LIMIT 1"));
    if ($row) {
        if ($row['image_path'] && file_exists(UPLOAD_DIR . $row['image_path'])) {
            unlink(UPLOAD_DIR . $row['image_path']);
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM reports WHERE report_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $del_id);
        mysqli_stmt_execute($stmt);
        $message = 'success:Report #' . $del_id . ' deleted.';
    }
}

// ── Filters ─────────────────────────────────────────────────────────────────
$filter_status   = $_GET['status']   ?? '';
$filter_category = (int)($_GET['category'] ?? 0);
$search          = trim($_GET['search'] ?? '');
$edit_id         = (int)($_GET['edit']   ?? 0);

// Build WHERE clause dynamically
$where_parts = ['1=1'];
$bind_types  = '';
$bind_values = [];

if ($filter_status) {
    $where_parts[] = 'r.status = ?';
    $bind_types   .= 's';
    $bind_values[] = $filter_status;
}
if ($filter_category) {
    $where_parts[] = 'r.category_id = ?';
    $bind_types   .= 'i';
    $bind_values[] = $filter_category;
}
if ($search) {
    $where_parts[] = '(r.title LIKE ? OR r.description LIKE ? OR u.full_name LIKE ?)';
    $bind_types   .= 'sss';
    $like = '%' . $search . '%';
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
}

$where = implode(' AND ', $where_parts);
$sql   = "SELECT r.*, c.name AS cat, u.full_name
          FROM reports r
          JOIN categories c ON r.category_id = c.category_id
          JOIN users u ON r.user_id = u.user_id
          WHERE $where
          ORDER BY r.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($bind_types) {
    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
}
mysqli_stmt_execute($stmt);
$reports = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Load categories for filter dropdown
$cats = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM categories ORDER BY name"), MYSQLI_ASSOC);

// If editing, load that specific report
$edit_report = null;
if ($edit_id) {
    $edit_report = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM reports WHERE report_id = $edit_id LIMIT 1"));
}

[$msg_type, $msg_text] = $message ? explode(':', $message, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Reports — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="margin:0">
<div class="d-flex" style="min-height:100vh">

    <!-- Sidebar (same as dashboard) -->
    <aside class="admin-sidebar" style="min-height:100vh">
        <div class="sidebar-brand"><h5>🌿 EcoReport</h5></div>
        <nav class="nav flex-column mt-2">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link active" href="manage_reports.php"><i class="fas fa-flag"></i> Manage Reports</a>
            <a class="nav-link" href="categories.php"><i class="fas fa-tags"></i> Categories</a>
            <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1rem">
            <a class="nav-link" href="../index.php"><i class="fas fa-globe"></i> View Site</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <div class="d-flex flex-column flex-grow-1">
        <div class="admin-topbar">
            <h6>Manage Reports</h6>
            <span class="text-muted small"><?= count($reports) ?> report(s) found</span>
        </div>

        <div class="admin-content">

            <?php if ($msg_text): ?>
            <div class="alert alert-eco alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> mb-4">
                <?= h($msg_text) ?>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <form method="GET" class="card-eco p-3 mb-4 form-eco">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               value="<?= h($search) ?>" placeholder="Title, description, user…">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending','in_progress','resolved','rejected'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_',' ',$s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= $c['category_id'] ?>"
                                    <?= $filter_category == $c['category_id'] ? 'selected' : '' ?>>
                                    <?= h($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-eco-primary w-100 btn-sm">Filter</button>
                    </div>
                </div>
            </form>

            <!-- Update Status Modal (shown when edit_id is set) -->
            <?php if ($edit_report): ?>
            <div class="card-eco mb-4" style="border:2px solid var(--green-mid)">
                <div class="card-header-eco">
                    <i class="fas fa-edit me-2"></i>Update Report #<?= $edit_id ?>:
                    <?= h(mb_substr($edit_report['title'], 0, 60)) ?>
                </div>
                <div class="p-4">
                    <form method="POST" class="form-eco">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="report_id"     value="<?= $edit_id ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">New Status</label>
                                <select name="new_status" class="form-select" required>
                                    <?php foreach (['pending','in_progress','resolved','rejected'] as $s): ?>
                                        <option value="<?= $s ?>"
                                            <?= $edit_report['status'] === $s ? 'selected' : '' ?>>
                                            <?= ucwords(str_replace('_',' ',$s)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Status Change Note (logged)</label>
                                <input type="text" name="note" class="form-control"
                                       placeholder="e.g. Dispatched cleanup crew">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Admin Notes (visible to citizen)</label>
                                <textarea name="admin_notes" class="form-control" rows="2"
                                          placeholder="Any message for the reporter…"><?= h($edit_report['admin_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-eco-primary">Save Changes</button>
                                <a href="manage_reports.php" class="btn btn-eco-outline">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reports Table -->
            <div class="card-eco">
                <div class="table-responsive">
                    <table class="table table-eco mb-0">
                        <thead>
                            <tr>
                                <th>#</th><th>Photo</th><th>Title</th><th>Category</th>
                                <th>Reporter</th><th>Priority</th><th>Status</th>
                                <th>Date</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No reports found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($reports as $r): ?>
                            <tr>
                                <td class="text-muted small"><?= $r['report_id'] ?></td>
                                <td>
                                    <?php if ($r['image_path']): ?>
                                        <img src="<?= UPLOAD_URL . h($r['image_path']) ?>"
                                             style="width:48px;height:48px;object-fit:cover;border-radius:6px"
                                             alt="">
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:200px">
                                    <strong><?= h(mb_substr($r['title'],0,50)) ?></strong>
                                    <?php if ($r['address']): ?>
                                    <br><small class="text-muted"><i class="fas fa-map-marker-alt"></i>
                                        <?= h(mb_substr($r['address'],0,40)) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= h($r['cat']) ?></span></td>
                                <td><?= h($r['full_name']) ?></td>
                                <td><?= priority_badge($r['priority']) ?></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td class="text-muted small"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="?edit=<?= $r['report_id'] ?>"
                                           class="btn btn-sm btn-eco-primary py-0 px-2">Edit</a>
                                        <a href="?delete=<?= $r['report_id'] ?>"
                                           class="btn btn-sm btn-outline-danger py-0 px-2"
                                           onclick="return confirm('Delete this report? This cannot be undone.')">
                                            Del
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
