<?php

// ── Database credentials ──────────────────────────────────────────────────────
// Change these to match your local setup.
define('DB_HOST',     'localhost');
define('DB_USER',     'root');          // your MySQL username
define('DB_PASS',     '');              // your MySQL password
define('DB_NAME',     'env_reporting');
define('DB_CHARSET',  'utf8mb4');

// ── Application settings ──────────────────────────────────────────────────────
define('SITE_NAME',   'EcoReport');
define('SITE_URL',    'http://localhost/envreport');
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');   // absolute path for file operations
define('UPLOAD_URL',  SITE_URL . '/uploads/');     // public URL for displaying images
define('MAX_FILE_SIZE', 5 * 1024 * 1024);          // 5 MB maximum upload size

// ── Maps ──────────────────────────────────────────────────────────────────────
// No API key needed! This project uses Leaflet.js + OpenStreetMap (100% free).
// The map works out of the box with no configuration.

// ── Create the connection ─────────────────────────────────────────────────────
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check that the connection worked
if (!$conn) {
    // die() stops the script and shows a message.
    // In production you would log the error instead of displaying it.
    die('<h3 style="color:red">Database connection failed: '
        . mysqli_connect_error() . '</h3>');
}

// Set the character set so emojis and special characters work correctly
mysqli_set_charset($conn, DB_CHARSET);
?>
