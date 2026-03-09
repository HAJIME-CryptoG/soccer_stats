-- AmisterStats Database Schema
-- 選手・行為・試合のマスターデータとログを管理するテーブル定義

CREATE DATABASE IF NOT EXISTS amisterstats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amisterstats;

-- 選手マスター
CREATE TABLE IF NOT EXISTS players (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    number      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    team        VARCHAR(100) NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 行為マスター（ポイント付き）
CREATE TABLE IF NOT EXISTS actions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    point_value TINYINT NOT NULL DEFAULT 1,   -- 正: 加点, 負: 減点
    category    ENUM('positive','negative') NOT NULL DEFAULT 'positive',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 試合マスター
CREATE TABLE IF NOT EXISTS matches (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_date  DATE NOT NULL,
    opponent    VARCHAR(100) NOT NULL DEFAULT '',
    location    VARCHAR(100) NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ログ（選手×行為×試合）
CREATE TABLE IF NOT EXISTS logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id    INT UNSIGNED NOT NULL,
    player_id   INT UNSIGNED NOT NULL,
    action_id   INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id)  REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- play_logs — 記録入力画面（record.php）からの一括保存テーブル
--
-- 構造:
--   batch_id    : 同一「登録」操作のグループID（Undo に使用）
--   player_name : 選手名（hiragana固定12名）
--   action      : 行為名
--   phase       : 'offense' | 'defense'
--   created_at  : 自動タイムスタンプ
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS play_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id    VARCHAR(64)  NOT NULL,
    player_name VARCHAR(50)  NOT NULL,
    action      VARCHAR(100) NOT NULL,
    phase       ENUM('offense','defense') NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch   (batch_id),
    INDEX idx_created (created_at),
    INDEX idx_player  (player_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- サンプルデータ（行為マスター）
INSERT INTO actions (name, point_value, category) VALUES
    ('パス成功',        1, 'positive'),
    ('シュート',        2, 'positive'),
    ('ゴール',          5, 'positive'),
    ('タックル成功',    2, 'positive'),
    ('インターセプト',  2, 'positive'),
    ('ドリブル突破',    2, 'positive'),
    ('アシスト',        3, 'positive'),
    ('クリア',          1, 'positive'),
    ('パスミス',       -1, 'negative'),
    ('ファウル',       -1, 'negative'),
    ('オフサイド',     -1, 'negative'),
    ('シュートミス',   -1, 'negative');
