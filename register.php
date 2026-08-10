<?php
/**
 * User Registration
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// If the user is already logged in, send them home
if (is_logged_in()) {
    redirect(SITE_URL . '/index.php');
}

$errors  = [];   // will hold any validation error messages
$success = '';   // success message after registration

// ── Process the form when it is submitted ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Read and sanitize inputs
    //    trim() removes leading/trailing spaces
    //    mysqli_real_escape_string() prevents SQL injection for non-prepared queries
    //    (We'll use prepared statements below, but cleaning is still good practice)
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';

    // 2. Validate inputs
    if (empty($full_name))  $errors[] = 'Full name is required.';
    if (strlen($full_name) > 150) $errors[] = 'Name is too long (max 150 characters).';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    if (strlen($password) < 8)        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)       $errors[] = 'Passwords do not match.';

    // 3. Check the email is not already registered
    //    We use a PREPARED STATEMENT to safely insert the email into the query
    if (empty($errors)) {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, 's', $email);   // 's' = string type
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $errors[] = 'That email address is already registered. Please log in instead.';
        }
        mysqli_stmt_close($check);
    }

    // 4. If no errors, save the new user
    if (empty($errors)) {
        // password_hash() creates a secure bcrypt hash — NEVER store plain-text passwords!
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = mysqli_prepare($conn,
            "INSERT INTO users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $full_name, $email, $phone, $hash);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Account created! You can now <a href="login.php">log in</a>.';
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
    <title>Citizen Registration — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-wrap" style="background:var(--navy)">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div style="width:100%;max-width:460px">

        <div class="text-center mb-4">
            <a href="index.php" class="text-decoration-none">
                <h2 style="color:var(--green-light);font-family:var(--font-display);font-weight:800">
                    🌿 EcoReport
                </h2>
            </a>
            <p style="color:rgba(255,255,255,.6)" class="mb-0">Create Citizen Account</p>
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
                <!-- novalidate lets PHP validate instead of the browser,
                     so we can show custom styled error messages -->

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= h($_POST['full_name'] ?? '') ?>"
                           placeholder="Jane Smith" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="jane@example.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= h($_POST['phone'] ?? '') ?>"
                           placeholder="+94 77 123 4567">
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwd" class="form-control"
                               placeholder="Min. 8 characters" required>
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePwd('pwd', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm" class="form-control"
                           placeholder="Repeat your password" required>
                </div>

                <button type="submit" class="btn btn-eco-primary w-100 py-2">
                    Create Citizen Account
                </button>
            </form>
            <hr class="my-3">
            <div class="text-center small">
                <a href="admin/register.php" style="color:var(--green-light)">Register Admin Account</a>
            </div>
        </div>

        <p class="text-center mt-3" style="color:rgba(255,255,255,.6)">
            Already have an account? <a href="login.php" style="color:var(--green-light)">Log In</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
</script>
</body>
</html>
