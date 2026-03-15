<?php
/**
 * debug_check.php — 接続診断スクリプト
 * 確認後は必ず削除してください（セキュリティリスク）
 */
header('Content-Type: application/json; charset=utf-8');

// ブラウザから直接アクセスして結果を確認する
$result = [];

// PHP バージョン確認
$result['php_version'] = PHP_VERSION;
$result['php_ok']      = version_compare(PHP_VERSION, '7.4.0', '>=');

// DB接続確認
require_once __DIR__ . '/../db/connect.php';
$result['db_host'] = DB_HOST;
$result['db_name'] = DB_NAME;
$result['db_user'] = DB_USER;

// 直接接続テスト（connect.phpを経由しない）
try {
    $direct = new PDO(
        'mysql:host=mysql10087.xserver.jp;dbname=xs228925_soccerstats;charset=utf8mb4',
        'xs228925_hajime',
        'soccer2024',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $result['direct_connect'] = 'OK';
} catch (Exception $e) {
    $result['direct_connect'] = 'FAILED: ' . $e->getMessage();
}

try {
    $pdo = get_pdo();
    $result['db_connect'] = 'OK';

    // play_logs テーブルの存在確認
    $stmt = $pdo->query("SHOW TABLES LIKE 'play_logs'");
    $result['table_play_logs'] = $stmt->rowCount() > 0 ? '存在する' : '存在しない → schema.sqlを実行してください';

} catch (Exception $e) {
    $result['db_connect'] = 'FAILED';
    $result['db_error']   = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
