<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理 — 管理画面</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #1a1a2e; color: #ddd; }
        .navbar { background: #16213e; border-bottom: 1px solid #0f3460; }
        .navbar-brand, .nav-link { color: #e94560 !important; }
        .nav-link:hover { color: #fff !important; }
        .card { background: #16213e; border: 1px solid #0f3460; border-radius: 12px; }
        .form-control, .form-select { background: #0f3460; border: 1px solid #4a90e2; color: #fff; }
        .form-control:focus, .form-select:focus { background: #0f3460; color: #fff; border-color: #e94560; box-shadow: none; }
        .table { color: #ddd; }
        .table thead th { color: #e94560; border-color: #0f3460; }
        .table td { border-color: #0f3460; vertical-align: middle; }
        .badge-admin { background: #e94560 !important; }
        .badge-user  { background: #0f3460 !important; border: 1px solid #4a90e2; }
        #toast-container { position: fixed; bottom: 1rem; left: 50%; transform: translateX(-50%); z-index: 9999; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-camera"></i> 写真AR管理</a>
        <div class="d-flex gap-2">
            <a class="nav-link" href="index.php"><i class="bi bi-images"></i> 写真</a>
            <a class="nav-link" href="map.php"><i class="bi bi-map"></i> 地図</a>
            <a class="nav-link" href="#" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 py-3">
    <h5 class="text-light mb-3"><i class="bi bi-people"></i> ユーザー管理</h5>

    <!-- ユーザー追加フォーム -->
    <div class="card mb-4 p-3">
        <h6 class="text-light mb-3">新規ユーザー追加</h6>
        <div class="row g-2">
            <div class="col-12 col-sm-4">
                <input type="text" id="newUsername" class="form-control" placeholder="ユーザー名">
            </div>
            <div class="col-12 col-sm-4">
                <input type="password" id="newPassword" class="form-control" placeholder="パスワード（6文字以上）">
            </div>
            <div class="col-6 col-sm-2">
                <select id="newRole" class="form-select">
                    <option value="user">一般</option>
                    <option value="admin">管理者</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <button class="btn btn-danger w-100" id="addUserBtn">
                    <i class="bi bi-person-plus"></i> 追加
                </button>
            </div>
        </div>
        <div id="add-error" class="text-danger mt-2" style="display:none;font-size:.875rem;"></div>
    </div>

    <!-- ユーザー一覧 -->
    <div class="card p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ユーザー名</th>
                        <th>ロール</th>
                        <th>作成日</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="user-list">
                    <tr><td colspan="5" class="text-center py-3">読み込み中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let myUserId = null;

async function init() {
    const res  = await fetch('../api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.loggedIn || data.role !== 'admin') { location.href = 'login.php'; return; }
    loadUsers();
}

async function loadUsers() {
    const res   = await fetch('../api/users.php', { credentials: 'include' });
    const data  = await res.json();
    const tbody = document.getElementById('user-list');

    if (!data.users || data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">ユーザーなし</td></tr>';
        return;
    }

    tbody.innerHTML = data.users.map(u => `
        <tr id="urow-${u.id}">
            <td class="text-muted">${u.id}</td>
            <td>${escHtml(u.username)}</td>
            <td><span class="badge ${u.role === 'admin' ? 'badge-admin' : 'badge-user'}">${u.role === 'admin' ? '管理者' : '一般'}</span></td>
            <td class="text-muted" style="font-size:.8rem">${u.created_at.slice(0,10)}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteUser(${u.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ユーザー追加
document.getElementById('addUserBtn').addEventListener('click', async () => {
    const username = document.getElementById('newUsername').value.trim();
    const password = document.getElementById('newPassword').value;
    const role     = document.getElementById('newRole').value;
    const errEl    = document.getElementById('add-error');
    errEl.style.display = 'none';

    const res  = await fetch('../api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password, role }),
        credentials: 'include'
    });
    const data = await res.json();

    if (data.ok) {
        document.getElementById('newUsername').value = '';
        document.getElementById('newPassword').value = '';
        showToast('ユーザーを追加しました', 'success');
        loadUsers();
    } else {
        errEl.textContent = data.error || '追加失敗';
        errEl.style.display = 'block';
    }
});

// ユーザー削除
async function deleteUser(id) {
    if (!confirm('このユーザーを削除しますか？')) return;
    const res  = await fetch(`../api/users.php?id=${id}`, { method: 'DELETE', credentials: 'include' });
    const data = await res.json();
    if (data.ok) {
        document.getElementById(`urow-${id}`)?.remove();
        showToast('削除しました', 'success');
    } else {
        showToast(data.error || '削除失敗', 'danger');
    }
}

// ログアウト
document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch('../api/auth.php?action=logout', { method: 'POST', credentials: 'include' });
    location.href = 'login.php';
});

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

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
