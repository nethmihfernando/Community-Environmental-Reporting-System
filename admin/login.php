<?php
/**
 * admin/login.php — Admin Portal Login
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/user/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        // Look up the user by email and ensure they are active
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Check password and verify the role is admin
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] !== 'admin') {
                $error = 'Access denied. Citizen accounts must log in via the Citizen Portal.';
            } else {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Store user info in the session
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];

                redirect(SITE_URL . '/admin/dashboard.php');
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="page-wrap" style="background:var(--navy)">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div style="width:100%;max-width:420px">

        <div class="text-center mb-4">
            <a href="../index.php" class="text-decoration-none">
                <h2 style="color:var(--green-light);font-family:var(--font-display);font-weight:800">
                    🌿 EcoReport Admin
                </h2>
            </a>
            <p style="color:rgba(255,255,255,.6)" class="mb-0">Administrator Portal</p>
        </div>

        <div class="card-eco p-4">
            <?php if ($error): ?>
                <div class="alert alert-eco alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="form-eco">
                <div class="mb-3">
                    <label class="form-label">Admin Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="admin@example.com" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwd" class="form-control"
                               placeholder="Admin password" required>
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePwd('pwd',this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-eco-primary w-100 py-2">Log In as Admin</button>
            </form>
            <hr class="my-3">
            <div class="text-center small">
                <a href="../login.php" style="color:var(--green-light)">Citizen Login Portal</a>
                <span class="text-muted mx-2">|</span>
                <a href="register.php" style="color:var(--green-light)">Register Admin Account</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
</script>
</body>
</html>
