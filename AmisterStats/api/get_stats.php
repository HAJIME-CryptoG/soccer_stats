<?php
// GET: match_id (任意), player_name (任意) → play_logs から集計データを JSON で返す
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connect.php';

/* ---- 行為リストは play_logs から動的取得 ---- */

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

    /* ---- 行為リストを play_logs から動的取得（全期間・全フィルター横断で軸を固定） ---- */
    $of_actions = $pdo->query(
        "SELECT DISTINCT action FROM play_logs WHERE phase='offense' ORDER BY action"
    )->fetchAll(PDO::FETCH_COLUMN);
    $df_actions = $pdo->query(
        "SELECT DISTINCT action FROM play_logs WHERE phase='defense' ORDER BY action"
    )->fetchAll(PDO::FETCH_COLUMN);

    /* ---- play_logsにデータがない場合は record.php と同じ固定リストをフォールバック ---- */
    if (empty($of_actions)) {
        $of_actions = ['アシスト','オフサイド','キーパス','センタリング','シュート内','シュート外','ドリブル失敗','ドリブル成功','パス失敗','パス成功'];
    }
    if (empty($df_actions)) {
        $df_actions = ['インターセプト','カバーリング','クリア(外)','クリア(相手)','クリア(味方)','シュートブロック','セービング','スプリント20m','ブレイクアウェイ','ボール奪取','パス成功/SK'];
    }

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

    /* ---- 登録済み選手を players テーブルから取得（0件でも表示するため） ---- */
    $registered = $pdo->query('SELECT name, number FROM players ORDER BY number ASC, name ASC')
                      ->fetchAll(PDO::FETCH_ASSOC);

    /* ---- 選手別に整形 ---- */
    $players = [];
    // まず登録済み選手を全員 0 で初期化
    foreach ($registered as $r) {
        $players[$r['name']] = [
            'name'    => $r['name'],
            'number'  => (int)$r['number'],
            'offense' => [],
            'defense' => [],
        ];
    }
    // play_logs の集計データをマージ
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

    /* ---- スパイダーチャート用データ（OF/DF別） ---- */
    $all_actions = array_merge($of_actions, $df_actions);
    foreach ($players as &$p) {
        // 全行為合計（後方互換）
        $counts = [];
        foreach ($all_actions as $act) {
            $counts[] = ($p['offense'][$act] ?? 0) + ($p['defense'][$act] ?? 0);
        }
        $p['chart_data']   = $counts;
        $p['total_count']  = array_sum($counts);
        $p['total_points'] = $p['total_count'];
        $p['actions']      = [];

        // オフェンス専用チャートデータ
        $of_counts = [];
        foreach ($of_actions as $act) {
            $of_counts[] = $p['offense'][$act] ?? 0;
        }
        $p['of_chart_data'] = $of_counts;

        // ディフェンス専用チャートデータ
        $df_counts = [];
        foreach ($df_actions as $act) {
            $df_counts[] = $p['defense'][$act] ?? 0;
        }
        $p['df_chart_data'] = $df_counts;
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
