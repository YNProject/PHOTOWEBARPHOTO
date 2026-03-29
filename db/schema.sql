-- =============================================
-- PHOTOWEBARPHOTO データベーススキーマ
-- =============================================

CREATE DATABASE IF NOT EXISTS photowar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE photowar_db;

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role        ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 写真テーブル
CREATE TABLE IF NOT EXISTS photos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    lat         DOUBLE NOT NULL,
    lng         DOUBLE NOT NULL,
    y_offset    FLOAT NOT NULL DEFAULT 1.0,
    aspect      FLOAT NOT NULL DEFAULT 1.0,
    image_path  VARCHAR(255) NOT NULL,
    comment     TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- コメントテーブル
CREATE TABLE IF NOT EXISTS comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    photo_id    INT NOT NULL,
    user_id     INT,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 初期管理者アカウントは手動で作成してください
-- php -r "echo password_hash('任意のパスワード', PASSWORD_BCRYPT);"
-- で生成したハッシュを使って以下を実行:
-- INSERT INTO users (username, password_hash, role) VALUES ('admin', '生成したハッシュ', 'admin');
