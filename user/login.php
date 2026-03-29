<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン — 写真AR</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background:#1a1a2e; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { background:#16213e; border:1px solid #0f3460; border-radius:16px; padding:2rem; width:100%; max-width:360px; }
        h1 { color:#e94560; font-size:1.4rem; text-align:center; margin-bottom:1.5rem; }
        .form-control { background:#0f3460; border:1px solid #e94560; color:#fff; }
        .form-control:focus { background:#0f3460; color:#fff; border-color:#e94560; box-shadow:0 0 0 .2rem rgba(233,69,96,.25); }
        .form-label { color:#ccc; }
        .btn-main { background:#e94560; border:none; width:100%; padding:.75rem; font-size:1rem; border-radius:8px; color:#fff; }
        .btn-main:hover { background:#c73652; color:#fff; }
        #error-msg { color:#ff6b6b; font-size:.875rem; text-align:center; margin-top:.75rem; display:none; }
        .sub-link { text-align:center; margin-top:1rem; font-size:.875rem; color:#aaa; }
        .sub-link a { color:#4a90e2; text-decoration:none; }
        .back-link { text-align:center; margin-top:.5rem; font-size:.8rem; }
        .back-link a { color:#888; text-decoration:none; }
    </style>
</head>
<body>
<div class="card">
    <h1>📸 写真AR ログイン</h1>
    <div class="mb-3">
        <label class="form-label">ユーザー名</label>
        <input type="text" id="username" class="form-control" placeholder="username" autocomplete="username">
    </div>
    <div class="mb-3">
        <label class="form-label">パスワード</label>
        <input type="password" id="password" class="form-control" placeholder="••••••••" autocomplete="current-password">
    </div>
    <button class="btn btn-main" id="loginBtn">ログイン</button>
    <div id="error-msg"></div>
    <div class="sub-link mt-3">
        アカウントをお持ちでない方は <a href="register.php">新規登録</a>
    </div>
    <div class="back-link mt-2">
        <a href="../index.html">← ARに戻る</a>
    </div>
</div>
<script>
async function doLogin() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const errEl    = document.getElementById('error-msg');
    errEl.style.display = 'none';

    if (!username || !password) {
        errEl.textContent = 'ユーザー名とパスワードを入力してください';
        errEl.style.display = 'block'; return;
    }
    try {
        const res  = await fetch('../api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password }),
            credentials: 'include'
        });
        const data = await res.json();
        if (data.ok) {
            location.href = data.role === 'admin' ? '../admin/index.php' : 'myphotos.php';
        } else {
            errEl.textContent = data.error || 'ログイン失敗';
            errEl.style.display = 'block';
        }
    } catch (e) {
        errEl.textContent = 'サーバーに接続できません';
        errEl.style.display = 'block';
    }
}
document.getElementById('loginBtn').addEventListener('click', doLogin);
document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>
