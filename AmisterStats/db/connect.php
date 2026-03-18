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
 *   - play_logs テーブルが無ければ作成
 *   - match_id カラムが無ければ追加
 *   - players テーブルが空なら初期12名を挿入
 */
function _run_migrations(PDO $pdo): void {
    // 1. play_logs テーブルを作成（存在しない場合）
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

    // 2. match_id カラムが無ければ追加
    $cols = $pdo->query("SHOW COLUMNS FROM play_logs LIKE 'match_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE play_logs ADD COLUMN match_id INT UNSIGNED NULL DEFAULT NULL");
    }

    // 3. players テーブルが空なら初期12名を挿入
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
        $stmt = $pdo->prepare(
            "INSERT INTO players (name, number, team) VALUES (?, ?, '')"
        );
        foreach ($initial as [$name, $number]) {
            $stmt->execute([$name, $number]);
        }
    }
}
