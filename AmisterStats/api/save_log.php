<?php
// POST: match_id, player_id, action_id → ログをINSERTしてポイントを返す
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connect.php';

try {
    $match_id  = filter_input(INPUT_POST, 'match_id',  FILTER_VALIDATE_INT);
    $player_id = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);
    $action_id = filter_input(INPUT_POST, 'action_id', FILTER_VALIDATE_INT);

    if (!$match_id || !$player_id || !$action_id) {
        http_response_code(400);
        echo json_encode(['error' => 'パラメータが不正です']);
        exit;
    }

    $pdo = get_pdo();

    // 行為のポイント値を取得
    $stmt = $pdo->prepare('SELECT name, point_value FROM actions WHERE id = ?');
    $stmt->execute([$action_id]);
    $action = $stmt->fetch();
    if (!$action) {
        http_response_code(404);
        echo json_encode(['error' => '行為が見つかりません']);
        exit;
    }

    // ログをINSERT
    $stmt = $pdo->prepare(
        'INSERT INTO logs (match_id, player_id, action_id) VALUES (?, ?, ?)'
    );
    $stmt->execute([$match_id, $player_id, $action_id]);
    $log_id = $pdo->lastInsertId();

    echo json_encode([
        'success'     => true,
        'log_id'      => (int)$log_id,
        'action_name' => $action['name'],
        'point_value' => (int)$action['point_value'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
