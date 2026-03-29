<?php
// =============================================
// DB接続設定サンプル
// このファイルをコピーして config.php にリネームし、
// 各環境の値を入力してください。
// config.php は .gitignore で除外されています。
// =============================================

// --- ローカル(XAMPP)用 ---
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'photowar_db');
// define('UPLOAD_URL', '/Kadai/PHOTOWEBARPHOTO/uploads/');

// --- 本番サーバー用 ---
define('DB_HOST', 'YOUR_DB_HOST');        // 例: mysql3112.db.sakura.ne.jp
define('DB_USER', 'YOUR_DB_USER');        // 例: accountname_dbname
define('DB_PASS', 'YOUR_DB_PASSWORD');
define('DB_NAME', 'YOUR_DB_NAME');
define('UPLOAD_URL', '/PHOTOWEBARPHOTO/uploads/');

// アップロード先（__DIR__ からの相対パスなので変更不要）
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireLogin(): array {
    session_start();
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'ログインが必要です'], 401);
    }
    return [
        'id'   => $_SESSION['user_id'],
        'role' => $_SESSION['role'],
    ];
}

function requireAdmin(): array {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        jsonResponse(['error' => '管理者権限が必要です'], 403);
    }
    return $user;
}

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
