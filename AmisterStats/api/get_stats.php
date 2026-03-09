<?php
// GET: match_id (任意), player_id (任意) → 集計データをJSONで返す
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connect.php';

try {
    $pdo = get_pdo();

    $match_id  = filter_input(INPUT_GET, 'match_id',  FILTER_VALIDATE_INT);
    $player_id = filter_input(INPUT_GET, 'player_id', FILTER_VALIDATE_INT);

    // --- 行為ラベル一覧（スパイダーチャートの軸） ---
    $actions = $pdo->query('SELECT id, name, point_value, category FROM actions ORDER BY id')->fetchAll();

    // --- 選手ごとの行為カウント集計 ---
    $where  = [];
    $params = [];

    if ($match_id) {
        $where[]  = 'l.match_id = ?';
        $params[] = $match_id;
    }
    if ($player_id) {
        $where[]  = 'l.player_id = ?';
        $params[] = $player_id;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            p.id   AS player_id,
            p.name AS player_name,
            p.number,
            a.id   AS action_id,
            a.name AS action_name,
            a.point_value,
            a.category,
            COUNT(l.id)              AS action_count,
            COUNT(l.id) * a.point_value AS total_points
        FROM logs l
        JOIN players p ON p.id = l.player_id
        JOIN actions a ON a.id = l.action_id
        $whereSQL
        GROUP BY p.id, a.id
        ORDER BY p.id, a.id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 選手別にデータを整形
    $players = [];
    foreach ($rows as $row) {
        $pid = $row['player_id'];
        if (!isset($players[$pid])) {
            $players[$pid] = [
                'id'           => (int)$pid,
                'name'         => $row['player_name'],
                'number'       => (int)$row['number'],
                'total_points' => 0,
                'actions'      => [],
            ];
        }
        $players[$pid]['total_points'] += (int)$row['total_points'];
        $players[$pid]['actions'][(int)$row['action_id']] = [
            'action_id'    => (int)$row['action_id'],
            'action_name'  => $row['action_name'],
            'count'        => (int)$row['action_count'],
            'total_points' => (int)$row['total_points'],
            'category'     => $row['category'],
        ];
    }

    // スパイダーチャート用: 各選手の全行為カウントを配列化（0埋め）
    $action_ids = array_column($actions, 'id');
    foreach ($players as &$player) {
        $counts = [];
        foreach ($action_ids as $aid) {
            $counts[] = isset($player['actions'][$aid])
                ? $player['actions'][$aid]['count']
                : 0;
        }
        $player['chart_data'] = $counts;
    }
    unset($player);

    echo json_encode([
        'actions' => $actions,
        'players' => array_values($players),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
