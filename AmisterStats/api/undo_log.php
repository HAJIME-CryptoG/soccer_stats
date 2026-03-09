<?php
// POST: match_id, player_id → その選手の直近ログ1件を削除
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connect.php';

try {
    $match_id  = filter_input(INPUT_POST, 'match_id',  FILTER_VALIDATE_INT);
    $player_id = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);

    if (!$match_id || !$player_id) {
        http_response_code(400);
        echo json_encode(['error' => 'パラメータが不正です']);
        exit;
    }

    $pdo = get_pdo();

    // 直近のログIDを取得
    $stmt = $pdo->prepare(
        'SELECT l.id, a.name AS action_name, a.point_value
         FROM logs l
         JOIN actions a ON a.id = l.action_id
         WHERE l.match_id = ? AND l.player_id = ?
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT 1'
    );
    $stmt->execute([$match_id, $player_id]);
    $log = $stmt->fetch();

    if (!$log) {
        http_response_code(404);
        echo json_encode(['error' => '取り消すログがありません']);
        exit;
    }

    $pdo->prepare('DELETE FROM logs WHERE id = ?')->execute([$log['id']]);

    echo json_encode([
        'success'     => true,
        'deleted_id'  => (int)$log['id'],
        'action_name' => $log['action_name'],
        'point_value' => (int)$log['point_value'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
