<?php require_once '../api/config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>地図表示 — 管理画面</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #1a1a2e; color: #ddd; margin: 0; }
        .navbar { background: #16213e; border-bottom: 1px solid #0f3460; }
        .navbar-brand, .nav-link { color: #e94560 !important; }
        #map { width: 100%; height: calc(100vh - 56px); height: calc(100dvh - 56px); }
        .gm-info { max-width: 220px; }
        .gm-info img { width: 100%; border-radius: 8px; margin-bottom: .5rem; }
        .gm-info .meta { font-size: .75rem; color: #555; }
        .gm-info .comment { font-size: .85rem; color: #333; margin-top: .3rem; }
        /* 配置モード中は地図カーソルを十字に */
        #map.place-mode { cursor: crosshair !important; }
        /* 配置モードバナー */
        #place-banner {
            display: none;
            position: fixed; top: 56px; left: 0; right: 0; z-index: 200;
            background: #e94560; color: #fff;
            text-align: center; padding: .4rem;
            font-size: .875rem; font-weight: bold;
        }
        /* トースト */
        #toast {
            position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
            background: #0f3460; color: #fff; padding: .6rem 1.4rem;
            border-radius: 99px; font-size: .875rem;
            opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 9999;
        }
        #toast.show { opacity: 1; }
        /* 配置ボタン アクティブ */
        #placeToggle.active { background: #e94560 !important; color: #fff !important; border-color: #e94560 !important; }
        /* モーダル画像プレビュー */
        #imgPreview { width: 100%; border-radius: 8px; display: none; margin-top: .5rem; max-height: 180px; object-fit: cover; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-camera"></i> 写真AR管理</a>
        <div class="d-flex gap-2 align-items-center">
            <button id="placeToggle" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-pin-map"></i> 配置モード
            </button>
            <a class="nav-link" href="index.php"><i class="bi bi-images"></i> 写真</a>
            <a class="nav-link" id="usersLink" href="users.php" style="display:none"><i class="bi bi-people"></i> ユーザー</a>
            <a class="nav-link" href="../index.php" title="ARアプリ"><i class="bi bi-phone"></i></a>
            <a class="nav-link" href="#" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<!-- 配置モードバナー -->
<div id="place-banner">📌 配置モード ON — 地図をクリックして写真を置く場所を選んでください</div>

<div id="map"></div>

<!-- 写真配置モーダル -->
<div class="modal fade" id="placeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#16213e; color:#ddd; border:1px solid #0f3460;">
            <div class="modal-header" style="border-color:#0f3460;">
                <h5 class="modal-title"><i class="bi bi-pin-map-fill text-danger"></i> 写真を配置</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    📍 <span id="modalLatLng">--</span>
                </p>
                <!-- 写真選択 -->
                <div class="mb-3">
                    <label class="form-label">写真を選ぶ <span class="text-danger">*</span></label>
                    <input type="file" id="placeFile" class="form-control" accept="image/*" style="background:#0f3460;color:#ddd;border-color:#0f3460;">
                    <img id="imgPreview" alt="プレビュー">
                </div>
                <!-- コメント -->
                <div class="mb-3">
                    <label class="form-label">コメント（任意）</label>
                    <input type="text" id="placeComment" class="form-control" placeholder="この写真についてひとこと"
                        style="background:#0f3460;color:#ddd;border-color:#0f3460;">
                </div>
            </div>
            <div class="modal-footer" style="border-color:#0f3460;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" id="placeSubmit" class="btn btn-danger">
                    <i class="bi bi-upload"></i> ARに配置する
                </button>
            </div>
        </div>
    </div>
</div>

<!-- トースト -->
<div id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const GOOGLE_MAPS_API_KEY = '<?= MAPS_API_KEY ?>';

let map, infoWindow;
let placeModeOn = false;
let pendingLat = null, pendingLng = null;
let markers = [];
const placeModal = new bootstrap.Modal(document.getElementById('placeModal'));

// --- ユーティリティ ---
function showToast(msg, ms = 2500) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), ms);
}

// --- 配置モード切り替え ---
document.getElementById('placeToggle').addEventListener('click', () => {
    placeModeOn = !placeModeOn;
    document.getElementById('placeToggle').classList.toggle('active', placeModeOn);
    document.getElementById('place-banner').style.display = placeModeOn ? 'block' : 'none';
    document.getElementById('map').classList.toggle('place-mode', placeModeOn);
    if (placeModeOn) showToast('📌 配置モード ON — 地図をクリックして場所を選んでください');
});

// --- 写真プレビュー ---
document.getElementById('placeFile').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    const preview = document.getElementById('imgPreview');
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
});

// --- 配置ボタン送信 ---
document.getElementById('placeSubmit').addEventListener('click', async () => {
    const file    = document.getElementById('placeFile').files[0];
    const comment = document.getElementById('placeComment').value.trim();

    if (!file) { showToast('⚠️ 写真を選んでください'); return; }
    if (pendingLat === null) { showToast('⚠️ 地図をクリックして場所を選んでください'); return; }

    const btn = document.getElementById('placeSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 保存中...';

    try {
        // 画像をリサイズしてからアップロード
        const { blob, aspect } = await resizeImage(file, 1024);

        const formData = new FormData();
        formData.append('lat',      pendingLat);
        formData.append('lng',      pendingLng);
        formData.append('y_offset', 1.5);
        formData.append('aspect',   aspect);
        formData.append('image',    new File([blob], `admin_${Date.now()}.jpg`, { type: 'image/jpeg' }));
        if (comment) formData.append('comment', comment);

        const res  = await fetch('../api/photos.php', { method: 'POST', body: formData, credentials: 'include' });
        const data = await res.json();

        if (data.ok) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('placeModal')).hide();
            addMarker({ id: data.id, lat: pendingLat, lng: pendingLng, image_url: data.image_url, comment, created_at: new Date().toISOString(), username: 'admin' });
            showToast('✅ 配置しました！スマホのARアプリで確認できます');
            document.getElementById('placeFile').value = '';
            document.getElementById('placeComment').value = '';
            document.getElementById('imgPreview').style.display = 'none';
        } else {
            showToast('❌ ' + (data.error || '保存失敗'));
        }
    } catch (e) {
        showToast('❌ ' + (e.message || '通信エラー'));
        console.error(e);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> ARに配置する';
    }
});

// --- 画像リサイズ（Blob + aspect返却）---
function resizeImage(file, maxPx) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onerror = () => reject(new Error('画像の読み込みに失敗しました'));
        img.onload = () => {
            let w = img.width, h = img.height;
            const aspect = w / h;
            if (w > h && w > maxPx) { h = h * maxPx / w; w = maxPx; }
            else if (h > maxPx) { w = w * maxPx / h; h = maxPx; }
            const c = document.createElement('canvas');
            c.width = w; c.height = h;
            c.getContext('2d').drawImage(img, 0, 0, w, h);
            c.toBlob(blob => {
                if (!blob) { reject(new Error('画像変換に失敗しました')); return; }
                resolve({ blob, aspect });
            }, 'image/jpeg', 0.9);
        };
        img.src = URL.createObjectURL(file);
    });
}

// --- マーカー追加 ---
function addMarker(p) {
    const marker = new google.maps.Marker({
        position: { lat: parseFloat(p.lat), lng: parseFloat(p.lng) },
        map,
        title: p.comment || `写真 #${p.id}`,
        icon: {
            url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
            scaledSize: new google.maps.Size(32, 32)
        }
    });

    marker.addListener('click', () => {
        infoWindow.setContent(`
            <div class="gm-info">
                <img src="${p.image_url}" alt="写真">
                <div class="meta">
                    <b>${p.username || '管理者'}</b> · ${p.created_at.slice(0,10)}<br>
                    📍 ${parseFloat(p.lat).toFixed(5)}, ${parseFloat(p.lng).toFixed(5)}
                </div>
                ${p.comment ? `<div class="comment">💬 ${p.comment}</div>` : ''}
                <div style="margin-top:.5rem">
                    <a href="index.php" style="font-size:.8rem;color:#e94560">管理画面で編集 →</a>
                </div>
            </div>
        `);
        infoWindow.open(map, marker);
    });

    markers.push(marker);
}

// --- 初期化 ---
async function init() {
    const authRes  = await fetch('../api/auth.php?action=check', { credentials: 'include' });
    const authData = await authRes.json();
    if (!authData.loggedIn) { location.href = 'login.php'; return; }
    if (authData.role === 'admin') {
        document.getElementById('usersLink').style.display = '';
    }
    // adminなら全写真、一般ユーザーは公開＋自分の写真のみ
    window._photosEndpoint = authData.role === 'admin'
        ? '../api/photos.php?all=1'
        : '../api/photos.php';

    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&callback=initMap`;
    script.async = true;
    document.head.appendChild(script);
}

// --- Google Maps 初期化（同期的にマップ生成、写真は後から追加）---
window.initMap = function() {
    // マップを同期的に生成（Google Maps の要件）
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 35.6812, lng: 139.7671 },
        zoom: 14,
        mapTypeId: 'roadmap'
    });

    infoWindow = new google.maps.InfoWindow();

    // 地図クリック → 配置モード時にモーダル表示
    map.addListener('click', e => {
        if (!placeModeOn) return;
        pendingLat = e.latLng.lat();
        pendingLng = e.latLng.lng();
        document.getElementById('modalLatLng').textContent =
            `${pendingLat.toFixed(5)}, ${pendingLng.toFixed(5)}`;
        placeModal.show();
    });

    // 写真を非同期で読み込んでマーカー追加・中心移動
    fetch(window._photosEndpoint, { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            const photos = (data.photos || []).filter(p => {
                const lat = parseFloat(p.lat), lng = parseFloat(p.lng);
                return !isNaN(lat) && !isNaN(lng) && Math.abs(lat) > 0.01 && Math.abs(lng) > 0.01;
            });
            console.log('写真件数:', photos.length, photos.map(p => `${p.lat},${p.lng}`));
            photos.forEach(addMarker);
            if (photos.length > 0) {
                // 外れ値を除いて中央値付近の写真だけでfitBounds
                const lats = photos.map(p => parseFloat(p.lat)).sort((a, b) => a - b);
                const lngs = photos.map(p => parseFloat(p.lng)).sort((a, b) => a - b);
                const medLat = lats[Math.floor(lats.length / 2)];
                const medLng = lngs[Math.floor(lngs.length / 2)];
                const nearPhotos = photos.filter(p =>
                    Math.abs(parseFloat(p.lat) - medLat) < 3 &&
                    Math.abs(parseFloat(p.lng) - medLng) < 3
                );
                const bounds = new google.maps.LatLngBounds();
                (nearPhotos.length > 0 ? nearPhotos : photos)
                    .forEach(p => bounds.extend({ lat: parseFloat(p.lat), lng: parseFloat(p.lng) }));
                map.fitBounds(bounds);
            }
        })
        .catch(e => console.error('写真の読み込みに失敗:', e));
};

// --- ログアウト ---
document.getElementById('logoutBtn').addEventListener('click', async e => {
    e.preventDefault();
    await fetch('../api/auth.php?action=logout', { method: 'POST', credentials: 'include' });
    location.href = 'login.php';
});

init();
</script>
</body>
</html>
