<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$method = $_SERVER['REQUEST_METHOD'];

// 所有者チェック（自分の写真 or admin のみ操作可）
function requirePhotoOwner(int $id): array {
    $stmt = getDB()->prepare('SELECT id, user_id, image_path, is_public FROM photos WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['error' => '写真が見つかりません'], 404);

    $currentUser = $_SESSION['user_id'] ?? null;
    $currentRole = $_SESSION['role']    ?? '';
    if ($currentRole !== 'admin' && (int)$row['user_id'] !== (int)$currentUser) {
        jsonResponse(['error' => 'この操作を行う権限がありません'], 403);
    }
    return $row;
}

// =============================================
// GET /api/photos.php         → 公開写真のみ（ARアプリ用）
// GET /api/photos.php?all=1   → 全写真（管理画面用・admin必須）
// GET /api/photos.php?mine=1  → 自分の写真（ログイン必須）
// =============================================
if ($method === 'GET') {
    $showAll  = ($_GET['all']  ?? '') === '1';
    $showMine = ($_GET['mine'] ?? '') === '1';

    if ($showMine) {
        $user = requireLogin();
        $stmt = getDB()->prepare(
            'SELECT p.id, p.lat, p.lng, p.y_offset, p.aspect, p.image_path,
                    p.comment, p.created_at, p.is_public, u.username
             FROM photos p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$user['id']]);
    } elseif ($showAll) {
        requireAdmin(); // 全写真取得はadminのみ
        $stmt = getDB()->query(
            'SELECT p.id, p.lat, p.lng, p.y_offset, p.aspect, p.image_path,
                    p.comment, p.created_at, p.is_public, u.username
             FROM photos p
             LEFT JOIN users u ON u.id = p.user_id
             ORDER BY p.created_at DESC'
        );
    } else {
        // 公開 + ログイン中は自分のみ公開も含める
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $stmt = getDB()->prepare(
                'SELECT p.id, p.lat, p.lng, p.y_offset, p.aspect, p.image_path,
                        p.comment, p.created_at, p.is_public, u.username
                 FROM photos p
                 LEFT JOIN users u ON u.id = p.user_id
                 WHERE p.is_public = 1 OR (p.is_public = 2 AND p.user_id = ?)
                 ORDER BY p.created_at DESC'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = getDB()->query(
                'SELECT p.id, p.lat, p.lng, p.y_offset, p.aspect, p.image_path,
                        p.comment, p.created_at, p.is_public, u.username
                 FROM photos p
                 LEFT JOIN users u ON u.id = p.user_id
                 WHERE p.is_public = 1
                 ORDER BY p.created_at DESC'
            );
        }
    }

    $photos = $stmt->fetchAll();
    foreach ($photos as &$p) {
        $p['image_url'] = UPLOAD_URL . basename($p['image_path']);
    }
    jsonResponse(['photos' => $photos]);
}

// =============================================
// POST ?action=toggle&id=X → 公開・非公開切り替え（所有者 or admin）
// =============================================
if ($method === 'POST' && ($_GET['action'] ?? '') === 'toggle') {
    requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'IDが必要です'], 400);

    $row    = requirePhotoOwner($id);
    $cur    = (int)$row['is_public'];
    $newVal = ($cur + 1) % 3; // 0→1→2→0
    getDB()->prepare('UPDATE photos SET is_public = ? WHERE id = ?')->execute([$newVal, $id]);
    jsonResponse(['ok' => true, 'is_public' => $newVal]);
}

// =============================================
// POST ?action=update&id=X → コメント更新（所有者 or admin）
// =============================================
if ($method === 'PUT' || ($method === 'POST' && ($_GET['action'] ?? '') === 'update')) {
    requireLogin();
    $id   = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true);
    $comment = trim($body['comment'] ?? '');

    if (!$id) jsonResponse(['error' => 'IDが必要です'], 400);

    requirePhotoOwner($id);
    getDB()->prepare('UPDATE photos SET comment = ? WHERE id = ?')->execute([$comment, $id]);
    jsonResponse(['ok' => true]);
}

// =============================================
// POST → 写真追加
// =============================================
if ($method === 'POST') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = $_SESSION['user_id'] ?? null;

    $lat     = (float)($_POST['lat']      ?? 0);
    $lng     = (float)($_POST['lng']      ?? 0);
    $yOffset = (float)($_POST['y_offset'] ?? 1.0);
    $aspect  = (float)($_POST['aspect']   ?? 1.0);
    $comment = trim($_POST['comment']     ?? '');

    if ($lat === 0.0 && $lng === 0.0) jsonResponse(['error' => '座標が必要です'], 400);
    if (empty($_FILES['image']))       jsonResponse(['error' => '画像ファイルが必要です'], 400);

    $file    = $_FILES['image'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) jsonResponse(['error' => '許可されていないファイル形式です'], 400);

    $filename = uniqid('photo_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        jsonResponse(['error' => '画像の保存に失敗しました'], 500);
    }

    $stmt = getDB()->prepare(
        'INSERT INTO photos (user_id, lat, lng, y_offset, aspect, image_path, comment)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $lat, $lng, $yOffset, $aspect, $filename, $comment ?: null]);
    $id = getDB()->lastInsertId();

    jsonResponse(['ok' => true, 'id' => (int)$id, 'image_url' => UPLOAD_URL . $filename], 201);
}

// =============================================
// DELETE ?id=X → 写真削除（所有者 or admin）
// =============================================
if ($method === 'DELETE') {
    requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'IDが必要です'], 400);

    $row = requirePhotoOwner($id);
    if (file_exists(UPLOAD_DIR . basename($row['image_path']))) {
        unlink(UPLOAD_DIR . basename($row['image_path']));
    }
    getDB()->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => '不正なリクエスト'], 400);
