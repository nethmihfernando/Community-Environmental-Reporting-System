<?php

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
<div class="d-flex" style="min-height:100vh">
    <aside class="admin-sidebar" style="min-height:100vh">
        <div class="sidebar-brand"><h5>🌿 EcoReport</h5></div>
        <nav class="nav flex-column mt-2">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link" href="manage_reports.php"><i class="fas fa-flag"></i> Manage Reports</a>
            <a class="nav-link active" href="categories.php"><i class="fas fa-tags"></i> Categories</a>
            <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1rem">
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>
    <div class="d-flex flex-column flex-grow-1">
        <div class="admin-topbar"><h6>Manage Categories</h6></div>
        <div class="admin-content">
            <?php if ($mtext): ?>
            <div class="alert alert-eco alert-<?= $mtype === 'success' ? 'success' : 'danger' ?> mb-4">
                <?= $mtext ?>
            </div>
            <?php endif; ?>
            <div class="row g-4">
                <!-- Add Category Form -->
                <div class="col-md-4">
                    <div class="card-eco">
                        <div class="card-header-eco">Add Category</div>
                        <div class="p-4">
                            <form method="POST" class="form-eco">
                                <input type="hidden" name="add" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">FontAwesome Icon class</label>
                                    <input type="text" name="icon" class="form-control"
                                           value="fa-exclamation-triangle"
                                           placeholder="fa-trash">
                                    <small class="text-muted">
                                        Find icons at <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com</a>
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-eco-primary w-100">Add Category</button>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Categories Table -->
                <div class="col-md-8">
                    <div class="card-eco">
                        <div class="card-header-eco">Existing Categories</div>
                        <div class="table-responsive">
                            <table class="table table-eco mb-0">
                                <thead><tr><th>Icon</th><th>Name</th><th>Description</th><th>Reports</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($categories as $c): ?>
                                    <tr>
                                        <td><i class="fas <?= h($c['icon']) ?> text-eco"></i></td>
                                        <td><strong><?= h($c['name']) ?></strong></td>
                                        <td class="text-muted small"><?= h(mb_substr($c['description'],0,60)) ?></td>
                                        <td><span class="badge bg-secondary"><?= $c['report_count'] ?></span></td>
                                        <td>
                                            <?php if ($c['report_count'] == 0): ?>
                                            <a href="?delete=<?= $c['category_id'] ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Delete this category?')">Delete</a>
                                            <?php else: ?>
                                            <span class="text-muted small">In use</span>
                                            <?php endif; ?>
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
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>