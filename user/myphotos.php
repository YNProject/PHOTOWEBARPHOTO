<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイ写真 — 写真AR</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background:#1a1a2e; color:#ddd; }
        .navbar { background:#16213e; border-bottom:1px solid #0f3460; }
        .navbar-brand, .nav-link { color:#e94560 !important; }
        .nav-link:hover { color:#fff !important; }
        .card { background:#16213e; border:1px solid #0f3460; border-radius:12px; margin-bottom:1rem; }
        .card img { width:100%; height:180px; object-fit:cover; border-radius:12px 12px 0 0; }
        .card-body { padding:.75rem; }
        .btn-del { background:#e94560; border:none; color:#fff; }
        .btn-del:hover { background:#c73652; color:#fff; }
        .btn-comment { background:#0f3460; border:1px solid #4a90e2; color:#4a90e2; }
        .btn-comment:hover { background:#4a90e2; color:#fff; }
        .btn-toggle-on   { background:#0f3460; border:1px solid #28a745; color:#28a745; }
        .btn-toggle-on:hover   { background:#28a745; color:#fff; }
        .btn-toggle-self { background:#0f3460; border:1px solid #4a90e2; color:#4a90e2; }
        .btn-toggle-self:hover { background:#4a90e2; color:#fff; }
        .btn-toggle-off  { background:#0f3460; border:1px solid #888; color:#888; }
        .btn-toggle-off:hover  { background:#555; color:#fff; }
        .card.is-private { opacity:0.6; }
        .meta { font-size:.75rem; color:#888; }
        .comment-text { font-size:.85rem; color:#adf; background:#0d1b2a; border-radius:6px; padding:.4rem .6rem; margin-top:.4rem; }
        #toast-container { position:fixed; bottom:1rem; left:50%; transform:translateX(-50%); z-index:9999; }
        .spinner-wrap { display:flex; justify-content:center; padding:3rem 0; }
        .empty-state { text-align:center; padding:3rem 1rem; color:#888; }
        .empty-state i { font-size:3rem; display:block; margin-bottom:1rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="myphotos.php"><i class="bi bi-camera"></i> マイ写真</a>
        <div class="d-flex gap-2 align-items-center">
            <span class="text-secondary small" id="username-display"></span>
            <a class="nav-link" href="../admin/map.php" title="地図配置"><i class="bi bi-map"></i></a>
            <a class="nav-link" href="../index.php" title="ARアプリ"><i class="bi bi-phone"></i></a>
            <a class="nav-link" href="#" id="logoutBtn" title="ログアウト"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-light">投稿した写真</h5>
        <span class="badge bg-secondary" id="photo-count">読み込み中...</span>
    </div>
    <div class="row g-2" id="photo-grid">
        <div class="spinner-wrap col-12"><div class="spinner-border text-danger"></div></div>
    </div>
</div>

<!-- コメント編集モーダル -->
<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h6 class="modal-title">コメント編集</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="commentInput" class="form-control bg-dark text-light border-secondary"
                    rows="4" placeholder="コメントを入力..."></textarea>
                <input type="hidden" id="commentPhotoId">
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button class="btn btn-primary" id="saveCommentBtn">保存</button>
            </div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function init() {
    const res  = await fetch('../api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.loggedIn) { location.href = 'login.php'; return; }
    document.getElementById('username-display').textContent = data.username || '';
    loadPhotos();
}

async function loadPhotos() {
    const res    = await fetch('../api/photos.php?mine=1', { credentials: 'include' });
    const data   = await res.json();
    const grid   = document.getElementById('photo-grid');
    const photos = data.photos || [];
    document.getElementById('photo-count').textContent = `${photos.length}枚`;

    if (photos.length === 0) {
        grid.innerHTML = `
            <div class="empty-state col-12">
                <i class="bi bi-camera-fill"></i>
                まだ写真がありません。<br>
                <a href="../index.php" class="text-info mt-2 d-inline-block">ARアプリで撮影してみよう →</a>
            </div>`;
        return;
    }

    grid.innerHTML = photos.map(p => {
        const vi = visInfo(p.is_public);
        return `
        <div class="col-6 col-md-4 col-lg-3" id="card-${p.id}">
            <div class="card ${parseInt(p.is_public) === 0 ? 'is-private' : ''}">
                <img src="${p.image_url}" alt="写真" loading="lazy">
                <div class="card-body">
                    <div class="meta mb-1">
                        <i class="bi bi-geo-alt"></i> ${parseFloat(p.lat).toFixed(5)}, ${parseFloat(p.lng).toFixed(5)}<br>
                        <i class="bi bi-clock"></i> ${p.created_at.slice(0,10)}
                    </div>
                    ${p.comment ? `<div class="comment-text"><i class="bi bi-chat-dots"></i> ${escHtml(p.comment)}</div>` : ''}
                    <div class="d-flex gap-1 mt-2">
                        <button class="btn btn-sm ${vi.cls}"
                            id="vis-${p.id}" onclick="toggleVisibility(${p.id}, this)"
                            title="${vi.title}">
                            <i class="bi ${vi.icon}"></i>
                        </button>
                        <button class="btn btn-comment btn-sm flex-fill"
                            data-id="${p.id}" data-comment="${escHtml(p.comment || '')}"
                            onclick="openComment(this)">
                            <i class="bi bi-pencil"></i> コメント
                        </button>
                        <button class="btn btn-del btn-sm" onclick="deletePhoto(${p.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');
}

function visInfo(val) {
    val = parseInt(val);
    if (val === 1) return { cls: 'btn-toggle-on',   icon: 'bi-eye',          title: '公開中（タップで自分のみ）' };
    if (val === 2) return { cls: 'btn-toggle-self',  icon: 'bi-person-check', title: '自分のみ（タップで非公開）' };
    return               { cls: 'btn-toggle-off',   icon: 'bi-eye-slash',    title: '非公開（タップで公開）' };
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function deletePhoto(id) {
    if (!confirm('この写真を削除しますか？')) return;
    const res  = await fetch(`../api/photos.php?id=${id}`, { method: 'DELETE', credentials: 'include' });
    const data = await res.json();
    if (data.ok) {
        document.getElementById(`card-${id}`)?.remove();
        showToast('削除しました', 'success');
        const count = document.querySelectorAll('[id^="card-"]').length;
        document.getElementById('photo-count').textContent = `${count}枚`;
    } else {
        showToast(data.error || '削除失敗', 'danger');
    }
}

async function toggleVisibility(id, btn) {
    const res  = await fetch(`../api/photos.php?action=toggle&id=${id}`, { method: 'POST', credentials: 'include' });
    const data = await res.json();
    if (!data.ok) { showToast(data.error || '切り替え失敗', 'danger'); return; }
    const v = parseInt(data.is_public);
    const card = document.getElementById(`card-${id}`).querySelector('.card');
    card.classList.toggle('is-private', v === 0);
    const vi = visInfo(v);
    btn.className = `btn btn-sm ${vi.cls}`;
    btn.innerHTML = `<i class="bi ${vi.icon}"></i>`;
    btn.title = vi.title;
    showToast(['🔒 非公開', '✅ 公開', '👤 自分のみ'][v], 'success');
}

function openComment(btn) {
    document.getElementById('commentPhotoId').value = btn.dataset.id;
    document.getElementById('commentInput').value   = btn.dataset.comment;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('commentModal')).show();
}

document.getElementById('saveCommentBtn').addEventListener('click', async () => {
    const id      = document.getElementById('commentPhotoId').value;
    const comment = document.getElementById('commentInput').value.trim();
    const res = await fetch(`../api/photos.php?action=update&id=${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment }),
        credentials: 'include'
    });
    const data = await res.json();
    if (data.ok) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('commentModal')).hide();
        showToast('コメントを保存しました', 'success');
        loadPhotos();
    } else {
        showToast(data.error || '保存失敗', 'danger');
    }
});

document.getElementById('logoutBtn').addEventListener('click', async e => {
    e.preventDefault();
    await fetch('../api/auth.php?action=logout', { method: 'POST', credentials: 'include' });
    location.href = 'login.php';
});

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} py-2 px-3 shadow`;
    el.style.cssText = 'min-width:200px;text-align:center;border-radius:8px;';
    el.textContent = msg;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => el.remove(), 2500);
}

init();
</script>
</body>
</html>
