<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/user/dashboard.php');
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $password   = $_POST['password']       ?? '';
    $confirm    = $_POST['confirm']        ?? '';
    $setup_code = trim($_POST['setup_code'] ?? '');

    // Validate inputs
    if (empty($full_name))  $errors[] = 'Full name is required.';
    if (strlen($full_name) > 150) $errors[] = 'Name is too long.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8)        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)       $errors[] = 'Passwords do not match.';
    if ($setup_code !== 'admin123')   $errors[] = 'Invalid Admin Registration Code.';

    // Check if email already exists
    if (empty($errors)) {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, 's', $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $errors[] = 'That email address is already registered.';
        }
        mysqli_stmt_close($check);
    }

    // Save admin user
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $role = 'admin';

        $stmt = mysqli_prepare($conn,
            "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $full_name, $email, $phone, $hash, $role);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Admin account created! You can now <a href="login.php" style="color:var(--navy);font-weight:700">log in</a>.';
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Registration — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="page-wrap" style="background:var(--navy)">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div style="width:100%;max-width:460px">

        <div class="text-center mb-4">
            <a href="../index.php" class="text-decoration-none">
                <h2 style="color:var(--green-light);font-family:var(--font-display);font-weight:800">
                    🌿 EcoReport Admin
                </h2>
            </a>
            <p style="color:rgba(255,255,255,.6)" class="mb-0">Create Administrator Account</p>
        </div>

        <div class="card-eco p-4">
            <?php if ($success): ?>
                <div class="alert alert-eco alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-eco alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= h($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="form-eco" novalidate>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= h($_POST['full_name'] ?? '') ?>"
                           placeholder="Administrator Name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="admin@example.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= h($_POST['phone'] ?? '') ?>"
                           placeholder="+94 77 123 4567">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 8 chars" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm" class="form-control" placeholder="Repeat password" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Admin Registration Code *</label>
                    <input type="password" name="setup_code" class="form-control"
                           placeholder="Enter setup code to authorize" required>
                </div>

                <button type="submit" class="btn btn-eco-primary w-100 py-2">
                    Create Admin Account
                </button>
            </form>
            <hr class="my-3">
            <div class="text-center small">
                <a href="login.php" style="color:var(--green-light)">Back to Admin Login</a>
                <span class="text-muted mx-2">|</span>
                <a href="../register.php" style="color:var(--green-light)">Register as Citizen</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
