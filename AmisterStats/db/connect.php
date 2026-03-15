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
    return $pdo;
}
