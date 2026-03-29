<?php
require_once __DIR__ . '/config.php';
session_start();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- ログイン ---
if ($action === 'login' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        jsonResponse(['error' => 'ユーザー名とパスワードを入力してください'], 400);
    }

    $stmt = getDB()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'ユーザー名またはパスワードが違います'], 401);
    }

    session_regenerate_id(true); // セッション固定攻撃対策
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['username'] = $user['username'];
    jsonResponse(['ok' => true, 'role' => $user['role']]);
}

// --- 新規登録 ---
if ($action === 'register' && $method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        jsonResponse(['error' => 'ユーザー名とパスワードを入力してください'], 400);
    }
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        jsonResponse(['error' => 'ユーザー名は3〜30文字の英数字・アンダーバーのみ使えます'], 400);
    }
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'パスワードは6文字以上にしてください'], 400);
    }

    $stmt = getDB()->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'そのユーザー名は既に使われています'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = getDB()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
    $stmt->execute([$username, $hash]);
    $newId = (int)getDB()->lastInsertId();

    $_SESSION['user_id']  = $newId;
    $_SESSION['role']     = 'user';
    $_SESSION['username'] = $username;
    jsonResponse(['ok' => true], 201);
}

// --- ログアウト ---
if ($action === 'logout' && $method === 'POST') {
    session_destroy();
    jsonResponse(['ok' => true]);
}

// --- セッション確認 ---
if ($action === 'check') {
    if (!empty($_SESSION['user_id'])) {
        jsonResponse([
            'loggedIn' => true,
            'role'     => $_SESSION['role'],
            'userId'   => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? ''
        ]);
    }
    jsonResponse(['loggedIn' => false]);
}

jsonResponse(['error' => '不正なリクエスト'], 400);
