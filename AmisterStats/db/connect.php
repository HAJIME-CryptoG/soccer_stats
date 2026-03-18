<?php
// DB接続設定 — 本番環境では環境変数や .env ファイルで管理してください
define('DB_HOST', getenv('DB_HOST') ?: 'mysql10087.xserver.jp');
define('DB_NAME', getenv('DB_NAME') ?: 'xs228925_soccerstats');
define('DB_USER', getenv('DB_USER') ?: 'xs228925_hajime');
define('DB_PASS', getenv('DB_PASS') ?: 'soccer2024');
define('DB_CHARSET', 'utf8mb4');

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    _run_migrations($pdo);
    return $pdo;
}

/**
 * 自動マイグレーション:
 *   - 全テーブルを CREATE TABLE IF NOT EXISTS で作成
 *   - matches テーブルに match_type / tournament_name カラムを追加
 *   - play_logs テーブルに match_id カラムを追加
 *   - players テーブルが空なら初期12名を挿入
 */
function _run_migrations(PDO $pdo): void {

    // 1. players テーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS players (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            number     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            team       VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. actions テーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS actions (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL,
            point_value TINYINT NOT NULL DEFAULT 1,
            category    ENUM('positive','negative') NOT NULL DEFAULT 'positive',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. matches テーブル（match_type / tournament_name 含む）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS matches (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            match_date      DATE NOT NULL,
            opponent        VARCHAR(100) NOT NULL DEFAULT '',
            location        VARCHAR(100) NOT NULL DEFAULT '',
            match_type      ENUM('friendly','official') NOT NULL DEFAULT 'friendly',
            tournament_name VARCHAR(100) NOT NULL DEFAULT '',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3a. matches に match_type カラムが無ければ追加（既存DB対応・個別チェック）
    if (empty($pdo->query("SHOW COLUMNS FROM matches LIKE 'match_type'")->fetchAll())) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN match_type ENUM('friendly','official') NOT NULL DEFAULT 'friendly'");
    }
    if (empty($pdo->query("SHOW COLUMNS FROM matches LIKE 'tournament_name'")->fetchAll())) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN tournament_name VARCHAR(100) NOT NULL DEFAULT ''");
    }

    // 4. play_logs テーブル（match_id 含む）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS play_logs (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id    VARCHAR(64)  NOT NULL,
            player_name VARCHAR(50)  NOT NULL,
            action      VARCHAR(100) NOT NULL,
            phase       ENUM('offense','defense') NOT NULL,
            match_id    INT UNSIGNED NULL DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch   (batch_id),
            INDEX idx_created (created_at),
            INDEX idx_player  (player_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4a. play_logs に match_id カラムが無ければ追加（既存DB対応）
    $cols = $pdo->query("SHOW COLUMNS FROM play_logs LIKE 'match_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE play_logs ADD COLUMN match_id INT UNSIGNED NULL DEFAULT NULL");
    }

    // 5. players テーブルが空なら初期12名を挿入
    $count = (int)$pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();
    if ($count === 0) {
        $initial = [
            ['いぶき',   0],
            ['けいご',   0],
            ['けんゆう', 0],
            ['しおん',   0],
            ['しょうや', 0],
            ['そうすけ', 0],
            ['たいち',   0],
            ['とも',     0],
            ['とわ',     0],
            ['ひさと',   0],
            ['ゆうし',   0],
            ['りょう',   0],
        ];
        $stmt = $pdo->prepare("INSERT INTO players (name, number, team) VALUES (?, ?, '')");
        foreach ($initial as [$name, $number]) {
            $stmt->execute([$name, $number]);
        }
    }
}
