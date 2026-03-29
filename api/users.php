<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// =============================================
// GET /api/users.php  → ユーザー一覧（管理者のみ）
// =============================================
if ($method === 'GET') {
    requireAdmin();
    $stmt = getDB()->query('SELECT id, username, role, created_at FROM users ORDER BY id');
    jsonResponse(['users' => $stmt->fetchAll()]);
}

// =============================================
// POST /api/users.php  → ユーザー追加（管理者のみ）
// =============================================
if ($method === 'POST') {
    requireAdmin();
    $body     = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $role     = in_array($body['role'] ?? '', ['admin', 'user'], true) ? $body['role'] : 'user';

    if ($username === '' || strlen($password) < 6) {
        jsonResponse(['error' => 'ユーザー名と6文字以上のパスワードが必要です'], 400);
    }

    // 重複チェック
    $stmt = getDB()->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'そのユーザー名は既に使われています'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = getDB()->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hash, $role]);
    jsonResponse(['ok' => true, 'id' => (int)getDB()->lastInsertId()], 201);
}

// =============================================
// DELETE /api/users.php?id=X  → ユーザー削除（管理者のみ）
// =============================================
if ($method === 'DELETE') {
    $admin = requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'IDが必要です'], 400);
    if ($id === $admin['id']) jsonResponse(['error' => '自分自身は削除できません'], 400);

    getDB()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => '不正なリクエスト'], 400);
