// ── 1. Auto-dismiss alerts after 5 seconds ─────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // Find any alert with class "auto-dismiss" and fade it out
    setTimeout(() => {
        document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        });
    }, 5000);

    // ── 2. Image upload preview ───────────────────────────────────────────
    // Works for any <input type="file" id="imageInput"> + <img id="image-preview">
    const fileInput  = document.getElementById('imageInput');
    const preview    = document.getElementById('image-preview');
    const uploadZone = document.getElementById('upload-zone');

    if (fileInput && preview) {

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) { preview.style.display = 'none'; return; }

            // Client-side file size check (5 MB) — server also checks this
            if (file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum allowed size is 5 MB.');
                fileInput.value = '';
                preview.style.display = 'none';
                return;
            }

            // Use FileReader to show a local preview without uploading yet
            const reader = new FileReader();
            reader.onload = e => {
                preview.src          = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });

        // Drag-and-drop support for the upload zone
        if (uploadZone) {
            uploadZone.addEventListener('dragover', e => {
                e.preventDefault();
                uploadZone.classList.add('dragging');
            });
            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('dragging');
            });
            uploadZone.addEventListener('drop', e => {
                e.preventDefault();
                uploadZone.classList.remove('dragging');
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            });
            uploadZone.addEventListener('click', () => fileInput.click());
        }
    }

    // ── 3. Confirm before deleting ────────────────────────────────────────
    document.querySelectorAll('.btn-delete-confirm').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm('Delete this report? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

});


// ═══════════════════════════════════════════════════════════════════════════
// 4. initReportsMap()
//    Called by the Google Maps API once it loads (on map_view.php).
//    Reads the global `reportsData` array (JSON-injected by PHP)
//    and adds a colour-coded marker for each report.
// ═══════════════════════════════════════════════════════════════════════════
function initReportsMap() {

    const map = new google.maps.Map(document.getElementById('map-view-full'), {
        zoom:              8,
        center:            { lat: 7.8731, lng: 80.7718 },  // Default: Sri Lanka
        streetViewControl: false,
        mapTypeControl:    true,
    });

    // Choose a different coloured dot for each status
    const markerIcons = {
        pending:     'http://maps.google.com/mapfiles/ms/icons/yellow-dot.png',
        in_progress: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
        resolved:    'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
        rejected:    'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
    };

    if (typeof reportsData === 'undefined' || !reportsData.length) return;

    reportsData.forEach(report => {
        const pos = {
            lat: parseFloat(report.latitude),
            lng: parseFloat(report.longitude)
        };

        const marker = new google.maps.Marker({
            position: pos,
            map,
            title: report.title,
            icon:  markerIcons[report.status] || markerIcons.pending,
        });

        // Info window — shows when a marker is clicked
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div style="font-family:sans-serif;max-width:220px">
                    <strong style="font-size:13px">${report.title}</strong><br>
                    <span style="color:#666;font-size:12px">${report.category}</span><br>
                    <span style="font-size:12px">Status: <b>${report.status.replace('_',' ')}</b></span><br>
                    ${report.address
                        ? `<span style="font-size:11px;color:#888">📍 ${report.address}</span><br>`
                        : ''}
                    <a href="${report.url}" style="color:#1a4731;font-size:12px;font-weight:600">
                        View Report →
                    </a>
                </div>`
        });

        marker.addListener('click', () => infoWindow.open(map, marker));
    });
}
