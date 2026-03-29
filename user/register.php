<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 — 写真AR</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background:#1a1a2e; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { background:#16213e; border:1px solid #0f3460; border-radius:16px; padding:2rem; width:100%; max-width:360px; }
        h1 { color:#e94560; font-size:1.4rem; text-align:center; margin-bottom:1.5rem; }
        .form-control { background:#0f3460; border:1px solid #e94560; color:#fff; }
        .form-control:focus { background:#0f3460; color:#fff; border-color:#e94560; box-shadow:0 0 0 .2rem rgba(233,69,96,.25); }
        .form-label { color:#ccc; }
        .form-text { color:#888; font-size:.78rem; }
        .btn-main { background:#e94560; border:none; width:100%; padding:.75rem; font-size:1rem; border-radius:8px; color:#fff; }
        .btn-main:hover { background:#c73652; color:#fff; }
        #error-msg { color:#ff6b6b; font-size:.875rem; text-align:center; margin-top:.75rem; display:none; }
        .sub-link { text-align:center; margin-top:1rem; font-size:.875rem; color:#aaa; }
        .sub-link a { color:#4a90e2; text-decoration:none; }
    </style>
</head>
<body>
<div class="card">
    <h1>📸 新規登録</h1>
    <div class="mb-3">
        <label class="form-label">ユーザー名</label>
        <input type="text" id="username" class="form-control" placeholder="例: tanaka_taro" autocomplete="username">
        <div class="form-text">3〜30文字の英数字・アンダーバー（_）</div>
    </div>
    <div class="mb-3">
        <label class="form-label">パスワード</label>
        <input type="password" id="password" class="form-control" placeholder="6文字以上" autocomplete="new-password">
    </div>
    <div class="mb-3">
        <label class="form-label">パスワード（確認）</label>
        <input type="password" id="password2" class="form-control" placeholder="もう一度入力" autocomplete="new-password">
    </div>
    <button class="btn btn-main" id="registerBtn">登録する</button>
    <div id="error-msg"></div>
    <div class="sub-link mt-3">
        すでにアカウントをお持ちの方は <a href="login.php">ログイン</a>
    </div>
</div>
<script>
async function doRegister() {
    const username  = document.getElementById('username').value.trim();
    const password  = document.getElementById('password').value;
    const password2 = document.getElementById('password2').value;
    const errEl     = document.getElementById('error-msg');
    errEl.style.display = 'none';

    if (!username || !password || !password2) {
        errEl.textContent = 'すべての項目を入力してください'; errEl.style.display = 'block'; return;
    }
    if (password !== password2) {
        errEl.textContent = 'パスワードが一致しません'; errEl.style.display = 'block'; return;
    }
    if (password.length < 6) {
        errEl.textContent = 'パスワードは6文字以上にしてください'; errEl.style.display = 'block'; return;
    }
    if (!/^[a-zA-Z0-9_]{3,30}$/.test(username)) {
        errEl.textContent = 'ユーザー名は3〜30文字の英数字・アンダーバーのみ'; errEl.style.display = 'block'; return;
    }
    try {
        const res  = await fetch('../api/auth.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password }),
            credentials: 'include'
        });
        const data = await res.json();
        if (data.ok) {
            location.href = 'myphotos.php';
        } else {
            errEl.textContent = data.error || '登録失敗';
            errEl.style.display = 'block';
        }
    } catch (e) {
        errEl.textContent = 'サーバーに接続できません';
        errEl.style.display = 'block';
    }
}
document.getElementById('registerBtn').addEventListener('click', doRegister);
document.addEventListener('keydown', e => { if (e.key === 'Enter') doRegister(); });
</script>
</body>
</html>
