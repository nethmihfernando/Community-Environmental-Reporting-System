<?php
/**
 * Full-screen public map of all reports
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Fetch all non-rejected reports that have GPS coordinates
$sql = "SELECT r.report_id, r.title, r.latitude, r.longitude,
               r.status, r.address, c.name AS category
        FROM   reports r
        JOIN   categories c ON r.category_id = c.category_id
        WHERE  r.status   != 'rejected'
          AND  r.latitude  IS NOT NULL
          AND  r.longitude IS NOT NULL
        ORDER  BY r.created_at DESC";

$result      = mysqli_query($conn, $sql);
$reportsData = [];

while ($r = mysqli_fetch_assoc($result)) {
    $reportsData[] = [
        'id'       => $r['report_id'],
        'title'    => $r['title'],
        'lat'      => (float)$r['latitude'],
        'lng'      => (float)$r['longitude'],
        'status'   => $r['status'],
        'category' => $r['category'],
        'address'  => $r['address'] ?? '',
        'url'      => SITE_URL . '/user/view_report.php?id=' . $r['report_id'],
    ];
}

// Count by status for the legend badges
$counts = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(status='pending') AS pending,
            SUM(status='in_progress') AS in_progress,
            SUM(status='resolved') AS resolved
     FROM reports WHERE latitude IS NOT NULL"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Map — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Map takes up the full remaining screen height */
        #map-view-full {
            height: calc(100vh - 112px);
            width: 100%;
        }
        .legend-bar {
            background: var(--navy);
            padding: .6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: .4rem;
            color: rgba(255,255,255,.85);
            font-size: .82rem;
        }
        .legend-dot {
            width: 13px; height: 13px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid rgba(255,255,255,.4);
        }
        /* Custom popup styling */
        .leaflet-popup-content-wrapper {
            border-radius: 10px !important;
            font-family: 'DM Sans', sans-serif !important;
        }
        .popup-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; }
        .popup-cat   { color: #666; font-size: 11px; }
        .popup-status { font-size: 11px; margin: 4px 0; }
        .popup-addr  { color: #888; font-size: 11px; margin-bottom: 6px; }
        .popup-link  { color: #1a4731; font-weight: 700; font-size: 12px; }
    </style>
</head>
<body style="margin:0;overflow:hidden">

<!-- Navbar -->
<nav class="navbar navbar-eco" style="padding:.6rem 0">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
        <a class="navbar-brand" href="index.php">🌿 <span>Eco</span>Report</a>
        <div class="d-flex align-items-center gap-3">
            <?php if (is_logged_in()): ?>
                <a href="user/submit_report.php" class="btn btn-nav-cta btn-sm">
                    <i class="fas fa-plus me-1"></i>Report Issue
                </a>
                <a href="logout.php" class="nav-link text-white small">Logout</a>
            <?php else: ?>
                <a href="login.php"    class="nav-link text-white small">Login</a>
                <a href="register.php" class="btn btn-nav-cta btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Legend bar with live counts -->
<div class="legend-bar">
    <span class="legend-item">
        <span class="legend-dot" style="background:#f39c12"></span>
        Pending <strong class="ms-1">(<?= $counts['pending'] ?? 0 ?>)</strong>
    </span>
    <span class="legend-item">
        <span class="legend-dot" style="background:#3498db"></span>
        In Progress <strong class="ms-1">(<?= $counts['in_progress'] ?? 0 ?>)</strong>
    </span>
    <span class="legend-item">
        <span class="legend-dot" style="background:#2d7a4f"></span>
        Resolved <strong class="ms-1">(<?= $counts['resolved'] ?? 0 ?>)</strong>
    </span>
    <span class="legend-item ms-auto text-white-50">
        <i class="fas fa-map-marker-alt me-1"></i>
        <?= count($reportsData) ?> reports shown — click a marker for details
    </span>
</div>

<!-- The full-screen map -->
<div id="map-view-full"></div>

<!-- Inject PHP data into JavaScript safely -->
<script>
    const reportsData = <?= json_encode($reportsData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ═══════════════════════════════════════════════════════
//  LEAFLET OVERVIEW MAP
//  Draws colour-coded markers for every report
// ═══════════════════════════════════════════════════════

// Create the map — default centre: Sri Lanka
const map = L.map('map-view-full').setView([7.8731, 80.7718], 8);

// Free OpenStreetMap tile layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// ── Custom coloured marker icons per status ──────────────────
// We create small coloured circle icons using SVG (no image files needed)
function makeIcon(color) {
    return L.divIcon({
        className: '',   // no default Leaflet styling
        html: `<div style="
            width:18px; height:18px;
            background:${color};
            border-radius:50%;
            border:3px solid #fff;
            box-shadow:0 2px 6px rgba(0,0,0,.35)
        "></div>`,
        iconSize:   [18, 18],
        iconAnchor: [9, 9],     // centre of the circle
        popupAnchor:[0, -12]    // popup appears above the dot
    });
}

const icons = {
    pending:     makeIcon('#f39c12'),   // orange
    in_progress: makeIcon('#3498db'),   // blue
    resolved:    makeIcon('#2d7a4f'),   // green
    rejected:    makeIcon('#e74c3c'),   // red
};

// ── Place a marker for every report ──────────────────────────
reportsData.forEach(r => {
    const icon   = icons[r.status] || icons.pending;
    const marker = L.marker([r.lat, r.lng], { icon }).addTo(map);

    // Human-readable status label
    const statusLabel = r.status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());

    // Status badge colour
    const badgeColors = {
        pending:     '#f39c12',
        in_progress: '#3498db',
        resolved:    '#2d7a4f',
        rejected:    '#e74c3c',
    };
    const badgeColor = badgeColors[r.status] || '#999';

    // Popup HTML shown when user clicks the marker
    marker.bindPopup(`
        <div style="min-width:180px">
            <div class="popup-title">${r.title}</div>
            <div class="popup-cat"><i>📂 ${r.category}</i></div>
            <div class="popup-status">
                Status: <span style="
                    background:${badgeColor};
                    color:#fff;
                    padding:1px 7px;
                    border-radius:5px;
                    font-size:11px;
                    font-weight:600">${statusLabel}</span>
            </div>
            ${r.address
                ? `<div class="popup-addr">📍 ${r.address.substring(0, 60)}…</div>`
                : ''}
            <a href="${r.url}" class="popup-link">View Full Report →</a>
        </div>
    `, { maxWidth: 240 });
});

// ── Fit map view to show all markers ─────────────────────────
if (reportsData.length > 0) {
    const bounds = reportsData.map(r => [r.lat, r.lng]);
    map.fitBounds(bounds, { padding: [40, 40] });
}
</script>
</body>
</html>
