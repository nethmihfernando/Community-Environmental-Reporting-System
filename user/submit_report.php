<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$errors  = [];
$success = '';

$cats = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM categories ORDER BY name"), MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $priority    = $_POST['priority']   ?? 'medium';
    $latitude    = $_POST['latitude']   ?? '';
    $longitude   = $_POST['longitude']  ?? '';
    $address     = trim($_POST['address'] ?? '');

    if (empty($title))        $errors[] = 'Report title is required.';
    if (strlen($title) > 255) $errors[] = 'Title must be under 255 characters.';
    if (empty($description))  $errors[] = 'Description is required.';
    if ($category_id <= 0)    $errors[] = 'Please select a category.';
    if (!in_array($priority, ['low','medium','high'])) $priority = 'medium';

    if (!empty($latitude) && !empty($longitude)) {
        $lat = (float)$latitude;
        $lng = (float)$longitude;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $errors[] = 'Invalid map coordinates.';
            $latitude = $longitude = '';
        }
    } else {
        $latitude = $longitude = null;
    }

    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $file    = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
        } elseif (!in_array($file['type'], $allowed)) {
            $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed.';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Image must be smaller than 5 MB.';
        } else {
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'report_' . uniqid() . '.' . strtolower($ext);
            $dest     = UPLOAD_DIR . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $image_path = $filename;
            } else {
                $errors[] = 'Could not save the uploaded file.';
            }
        }
    }

    if (empty($errors)) {
        $user_id = $_SESSION['user_id'];
        $stmt = mysqli_prepare($conn,
            "INSERT INTO reports (user_id, category_id, title, description, image_path,
                                  latitude, longitude, address, priority)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iisssddss',
            $user_id, $category_id, $title, $description, $image_path,
            $latitude, $longitude, $address, $priority);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Report submitted successfully! <a href='my_reports.php'>View your reports</a>.";
            $title = $description = $address = '';
            $category_id = 0;
            $latitude = $longitude = '';
        } else {
            $errors[] = 'Database error. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Report — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS — the map styling -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* The map box */
        #map {
            height: 380px;
            width: 100%;
            border-radius: var(--radius-sm);
            z-index: 1;
        }
        /* Drag-and-drop upload zone */
        .upload-zone {
            border: 2px dashed var(--green-mid);
            border-radius: var(--radius-sm);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: var(--green-pale);
        }
        .upload-zone:hover { background: #d4eddf; }
        .upload-zone.dragging { background: #c8e6d0; border-color: var(--green-dark); }
    </style>
</head>
<body class="page-wrap">

<nav class="navbar navbar-expand-lg navbar-eco">
    <div class="container">
        <a class="navbar-brand" href="../index.php">🌿 <span>Eco</span>Report</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="dashboard.php"  class="nav-link text-white"><i class="fas fa-home me-1"></i>Dashboard</a>
            <a href="my_reports.php" class="nav-link text-white"><i class="fas fa-list me-1"></i>My Reports</a>
            <a href="../logout.php"  class="nav-link text-white"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<main class="main-wrap py-5">
<div class="container" style="max-width:780px">

    <div class="mb-4">
        <h1 class="section-title">Submit a Report</h1>
        <p class="text-muted mt-3">Help protect the environment by reporting issues in your area.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-eco alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-eco alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-eco" novalidate>

        <!-- ── Report Details ── -->
        <div class="card-eco mb-4">
            <div class="card-header-eco">
                <i class="fas fa-info-circle me-2"></i>Report Details
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <label class="form-label">Report Title *</label>
                    <input type="text" name="title" class="form-control"
                           value="<?= h($title ?? '') ?>"
                           placeholder="e.g. Illegal rubbish dumping near Riverside Park" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">— Select a category —</option>
                            <?php foreach ($cats as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"
                                    <?= ($category_id ?? 0) == $cat['category_id'] ? 'selected' : '' ?>>
                                    <?= h($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low"    <?= ($priority??'') === 'low'    ? 'selected':'' ?>>🟢 Low</option>
                            <option value="medium" <?= ($priority??'medium') === 'medium' ? 'selected':'' ?>>🟡 Medium</option>
                            <option value="high"   <?= ($priority??'') === 'high'   ? 'selected':'' ?>>🔴 High</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-control" rows="5"
                        placeholder="Describe what you saw in detail…" required><?= h($description ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── Photo Upload ── -->
        <div class="card-eco mb-4">
            <div class="card-header-eco">
                <i class="fas fa-camera me-2"></i>Photo Evidence
                <span class="fw-normal opacity-75 small ms-2">(optional, max 5 MB)</span>
            </div>
            <div class="p-4">
                <!-- Clickable / drag-and-drop zone -->
                <div class="upload-zone" id="upload-zone">
                    <i class="fas fa-cloud-upload-alt fa-2x text-eco mb-2"></i>
                    <p class="mb-1 fw-600">Drag &amp; drop a photo here</p>
                    <p class="text-muted small mb-0">or click to browse — JPEG, PNG, GIF, WebP</p>
                    <input type="file" name="image" id="imageInput"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           style="display:none">
                </div>
                <img id="image-preview" src="" alt="Preview"
                     style="display:none;max-height:200px;margin-top:1rem;
                            border-radius:var(--radius-sm);border:2px dashed var(--green-mid)">
            </div>
        </div>

        <!-- ── Map Location (Leaflet) ── -->
        <div class="card-eco mb-4">
            <div class="card-header-eco">
                <i class="fas fa-map-marker-alt me-2"></i>Pin Location on Map
            </div>
            <div class="p-4">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Click anywhere on the map to mark the exact location of the issue.
                    You can drag the pin to fine-tune it.
                </p>

                <!-- Hidden fields — filled by JavaScript when user clicks the map -->
                <input type="hidden" name="latitude"  id="lat_field"  value="<?= h($latitude  ?? '') ?>">
                <input type="hidden" name="longitude" id="lng_field"  value="<?= h($longitude ?? '') ?>">
                <input type="hidden" name="address"   id="addr_field" value="<?= h($address   ?? '') ?>">

                <div id="map"></div>

                <!-- Shows the selected coordinates below the map -->
                <div class="map-coords-display mt-0" id="coords-display">
                    📍 Click the map to select a location
                </div>
            </div>
        </div>

        <div class="d-flex gap-3">
            <button type="submit" class="btn btn-eco-primary px-5 py-2">
                <i class="fas fa-paper-plane me-2"></i>Submit Report
            </button>
            <a href="../index.php" class="btn btn-eco-outline py-2">Cancel</a>
        </div>
    </form>
</div>
</main>

<footer><p class="mb-0">© <?= date('Y') ?> <?= SITE_NAME ?></p></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS — the map library (no API key needed!) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ═══════════════════════════════════════════════════════
//  IMAGE UPLOAD PREVIEW + DRAG AND DROP
// ═══════════════════════════════════════════════════════
const uploadZone  = document.getElementById('upload-zone');
const fileInput   = document.getElementById('imageInput');
const preview     = document.getElementById('image-preview');

// Click on zone → open file picker
uploadZone.addEventListener('click', () => fileInput.click());

// When a file is chosen, show a preview
fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('File too large — maximum 5 MB.');
        fileInput.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        preview.src          = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// Drag-and-drop onto the zone
uploadZone.addEventListener('dragover',  e => { e.preventDefault(); uploadZone.classList.add('dragging'); });
uploadZone.addEventListener('dragleave', ()  => uploadZone.classList.remove('dragging'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('dragging');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});


// ═══════════════════════════════════════════════════════
//  LEAFLET MAP — LOCATION PICKER
//  No API key needed — uses free OpenStreetMap tiles
// ═══════════════════════════════════════════════════════

// Default centre: Sri Lanka — change lat/lng to your country if needed
const defaultLat = 7.8731;
const defaultLng = 80.7718;

// Create the map
const map = L.map('map').setView([defaultLat, defaultLng], 8);

// Add the free OpenStreetMap tile layer (the actual map images)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

let marker = null;  // will hold the draggable pin

// If coordinates were already saved (e.g. form re-shown after error), restore the pin
const savedLat = parseFloat(document.getElementById('lat_field').value);
const savedLng = parseFloat(document.getElementById('lng_field').value);
if (savedLat && savedLng) {
    placePin(savedLat, savedLng);
    map.setView([savedLat, savedLng], 14);
}

// Try to centre on the user's current GPS location
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
        map.setView([pos.coords.latitude, pos.coords.longitude], 13);
    });
}

// When the user clicks anywhere on the map, drop a pin there
map.on('click', function(e) {
    placePin(e.latlng.lat, e.latlng.lng);
    reverseGeocode(e.latlng.lat, e.latlng.lng);
});

// Place or move the draggable pin
function placePin(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);

        // When the user drags the pin, update coordinates too
        marker.on('dragend', function() {
            const pos = marker.getLatLng();
            updateCoords(pos.lat, pos.lng);
            reverseGeocode(pos.lat, pos.lng);
        });
    }
    updateCoords(lat, lng);
}

// Write lat/lng into the hidden form fields + update the display strip
function updateCoords(lat, lng) {
    document.getElementById('lat_field').value  = lat.toFixed(7);
    document.getElementById('lng_field').value  = lng.toFixed(7);
    document.getElementById('coords-display').textContent =
        '📍 Lat: ' + lat.toFixed(5) + '  |  Lng: ' + lng.toFixed(5);
}

// Use Nominatim (free, no key) to convert coordinates to a readable address
function reverseGeocode(lat, lng) {
    const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`;
    fetch(url, { headers: { 'Accept-Language': 'en' } })
        .then(r => r.json())
        .then(data => {
            if (data && data.display_name) {
                document.getElementById('addr_field').value = data.display_name;
            }
        })
        .catch(() => {}); // silently ignore if offline
}
</script>
</body>
</html>
