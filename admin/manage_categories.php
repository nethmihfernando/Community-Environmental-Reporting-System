<?php
/**
 * admin/manage_categories.php — Manage Report Categories
 * Fixed: uses correct constants (SITE_URL) and config includes
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$message = '';

// ── Add new category ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-exclamation-triangle');
    $desc = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $message = 'error:Category name is required.';
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $name, $desc, $icon);
        if (mysqli_stmt_execute($stmt)) {
            $message = 'success:Category "' . h($name) . '" added successfully.';
        } else {
            $message = 'error:Failed to add category. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}

// ── Delete category ───────────────────────────────────────────────────────────
if (!empty($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Check if any reports are using this category
    $count = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM reports WHERE category_id = $id"))['c'];

    if ($count > 0) {
        $message = 'error:Cannot delete — ' . $count . ' report(s) are using this category.';
    } else {
        $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = 'success:Category deleted successfully.';
    }
}

// ── Load all categories with report counts ────────────────────────────────────
$categories = mysqli_fetch_all(mysqli_query($conn,
    "SELECT c.*, COUNT(r.report_id) AS report_count
     FROM categories c
     LEFT JOIN reports r ON c.category_id = r.category_id
     GROUP BY c.category_id
     ORDER BY c.name ASC"), MYSQLI_ASSOC);

[$msg_type, $msg_text] = $message ? explode(':', $message, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Categories — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="margin:0">
<div class="d-flex" style="min-height:100vh">

    <!-- ── Sidebar ── -->
    <aside class="admin-sidebar" style="min-height:100vh">
        <div class="sidebar-brand">
            <h5>🌿 EcoReport</h5>
            <div style="color:rgba(255,255,255,.5);font-size:.78rem;margin-top:.3rem">Admin Panel</div>
        </div>
        <nav class="nav flex-column mt-2">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a class="nav-link" href="manage_reports.php">
                <i class="fas fa-flag"></i> Manage Reports
            </a>
            <a class="nav-link active" href="manage_categories.php">
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

    <!-- ── Main Content ── -->
    <div class="d-flex flex-column flex-grow-1">
        <div class="admin-topbar">
            <h6>Manage Categories</h6>
            <span class="text-muted small"><?= count($categories) ?> categories total</span>
        </div>

        <div class="admin-content">

            <?php if ($msg_text): ?>
            <div class="alert alert-eco alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> mb-4">
                <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= h($msg_text) ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Add Category Form -->
                <div class="col-md-4">
                    <div class="card-eco">
                        <div class="card-header-eco">
                            <i class="fas fa-plus me-2"></i>Add New Category
                        </div>
                        <div class="p-4">
                            <form method="POST" class="form-eco">
                                <input type="hidden" name="add_category" value="1">

                                <div class="mb-3">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" name="name" class="form-control"
                                           placeholder="e.g. Soil Contamination" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"
                                        placeholder="Brief description of this category"></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">FontAwesome Icon</label>
                                    <input type="text" name="icon" class="form-control"
                                           value="fa-exclamation-triangle"
                                           placeholder="fa-trash">
                                    <small class="text-muted">
                                        Find icons at
                                        <a href="https://fontawesome.com/icons" target="_blank">
                                            fontawesome.com
                                        </a>
                                    </small>
                                </div>

                                <button type="submit" class="btn btn-eco-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add Category
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Categories Table -->
                <div class="col-md-8">
                    <div class="card-eco">
                        <div class="card-header-eco">
                            <i class="fas fa-list me-2"></i>Existing Categories
                        </div>
                        <div class="table-responsive">
                            <table class="table table-eco mb-0">
                                <thead>
                                    <tr>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Reports</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $c): ?>
                                    <tr>
                                        <td>
                                            <i class="fas <?= h($c['icon']) ?> text-eco fa-lg"></i>
                                        </td>
                                        <td><strong><?= h($c['name']) ?></strong></td>
                                        <td class="text-muted small">
                                            <?= h(mb_substr($c['description'] ?? '', 0, 60)) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= $c['report_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($c['report_count'] == 0): ?>
                                                <a href="?delete=<?= $c['category_id'] ?>"
                                                   class="btn btn-sm btn-outline-danger py-0 px-2"
                                                   onclick="return confirm('Delete the category \'<?= h($c['name']) ?>\'?')">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <i class="fas fa-lock me-1"></i>In use
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No categories found.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div><!-- /admin-content -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>