<?php
// GET: match_id (任意), player_name (任意) → play_logs から集計データを JSON で返す
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connect.php';

/* ---- 行為名の固定リスト（record.php と一致） ---- */
$of_actions = [
    'パス成功', 'パス失敗', 'キーパス', 'アシスト', 'センタリング',
    'シュート内', 'シュート外', 'ドリブル成功', 'ドリブル失敗', 'オフサイド',
];
$df_actions = [
    'ボール奪取', 'インターセプト', 'シュートブロック', 'クリア(味方)',
    'セービング', 'ブレイクアウェイ', 'パス成功/SK', 'カバーリング',
    'スプリント20m', 'クリア(相手)', 'クリア(外)',
];

try {
    $pdo = get_pdo();

    $match_id    = filter_input(INPUT_GET, 'match_id',  FILTER_VALIDATE_INT) ?: null;
    $player_name = trim($_GET['player_name'] ?? '');

    /* ---- WHERE 条件 ---- */
    $where  = [];
    $params = [];

    if ($match_id) {
        $where[]  = 'match_id = ?';
        $params[] = $match_id;
    }
    if ($player_name !== '') {
        $where[]  = 'player_name = ?';
        $params[] = $player_name;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ---- play_logs から集計 ---- */
    $sql = "
        SELECT player_name, action, phase, COUNT(*) AS cnt
        FROM play_logs
        $whereSQL
        GROUP BY player_name, action, phase
        ORDER BY player_name, phase, action
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    /* ---- 選手別に整形 ---- */
    $players = [];
    foreach ($rows as $row) {
        $name = $row['player_name'];
        if (!isset($players[$name])) {
            $players[$name] = [
                'name'    => $name,
                'number'  => 0,
                'offense' => [],
                'defense' => [],
            ];
        }
        $key = ($row['phase'] === 'offense') ? 'offense' : 'defense';
        $players[$name][$key][$row['action']] = (int)$row['cnt'];
    }

    /* ---- スパイダーチャート用 chart_data（全行為の合計カウント） ---- */
    $all_actions = array_merge($of_actions, $df_actions);
    foreach ($players as &$p) {
        $counts = [];
        foreach ($all_actions as $act) {
            $counts[] = ($p['offense'][$act] ?? 0) + ($p['defense'][$act] ?? 0);
        }
        $p['chart_data']    = $counts;
        $p['total_count']   = array_sum($counts);
        $p['total_points']  = $p['total_count'];  // 後方互換
        $p['actions']       = [];                 // 後方互換
    }
    unset($p);

    echo json_encode([
        'of_actions' => $of_actions,
        'df_actions' => $df_actions,
        'players'    => array_values($players),
        /* 後方互換: スパイダーチャートが actions[].name を参照する */
        'actions'    => array_map(
            fn($a) => ['id' => $a, 'name' => $a, 'point_value' => 1, 'category' => 'positive'],
            $all_actions
        ),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
