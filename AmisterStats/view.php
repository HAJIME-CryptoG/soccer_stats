<?php
// 集計画面 — Chart.js によるスパイダーチャートと集計テーブル
require_once __DIR__ . '/db/connect.php';

try {
    $pdo = get_pdo();
    $matches = $pdo->query(
        'SELECT id, match_date, opponent FROM matches ORDER BY match_date DESC, id DESC'
    )->fetchAll();
    $players = $pdo->query(
        'SELECT id, name, number FROM players ORDER BY number ASC'
    )->fetchAll();
    $db_ok = true;
} catch (Exception $e) {
    $db_ok    = false;
    $db_error = $e->getMessage();
}

$filter_match_id  = $_GET['match_id']  ?? '';
$filter_player_id = $_GET['player_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1565c0">
    <title>集計を見る — AmisterStats</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <header class="app-header" style="background:#1565c0;">
        <a class="back-btn" href="index.php" aria-label="ホームへ戻る">&#8592;</a>
        <h1>&#128202; 集計を見る</h1>
    </header>

    <main class="container">
        <?php if (!$db_ok): ?>
            <div class="flash error">DB接続エラー: <?= htmlspecialchars($db_error) ?></div>
        <?php else: ?>

        <!-- フィルター -->
        <div class="card">
            <div class="card-title">絞り込み</div>
            <form method="get" action="view.php">
                <div class="form-group">
                    <label for="f-match">試合</label>
                    <select id="f-match" name="match_id">
                        <option value="">全試合</option>
                        <?php foreach ($matches as $m): ?>
                            <option value="<?= $m['id'] ?>"
                                <?= $filter_match_id == $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['match_date']) ?> vs <?= htmlspecialchars($m['opponent']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="f-player">選手</label>
                    <select id="f-player" name="player_id">
                        <option value="">全選手</option>
                        <?php foreach ($players as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                <?= $filter_player_id == $p['id'] ? 'selected' : '' ?>>
                                #<?= (int)$p['number'] ?> <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary btn-block" type="submit">集計を更新</button>
            </form>
        </div>

        <!-- スパイダーチャート -->
        <div class="card">
            <div class="card-title">スパイダーチャート（行為カウント）</div>
            <div class="chart-wrapper">
                <canvas id="spider-chart"></canvas>
            </div>
            <p id="chart-empty" class="text-muted text-center mt-2" style="font-size:.85rem;display:none;">
                表示するデータがありません
            </p>
        </div>

        <!-- 集計テーブル -->
        <div class="card">
            <div class="card-title">選手別 合計ポイント</div>
            <div id="stats-table-wrapper">
                <div class="spinner"></div>
            </div>
        </div>

        <?php endif; ?>
    </main>

    <div id="toast"></div>
    <script src="js/chart_config.js"></script>
    <script>
    (function () {
        const matchId  = <?= json_encode($filter_match_id  ?: null) ?>;
        const playerId = <?= json_encode($filter_player_id ?: null) ?>;

        // クエリパラメータ組み立て
        const params = new URLSearchParams();
        if (matchId)  params.set('match_id',  matchId);
        if (playerId) params.set('player_id', playerId);

        const apiUrl = 'api/get_stats.php' + (params.toString() ? '?' + params.toString() : '');

        fetch(apiUrl)
            .then(r => r.json())
            .then(data => {
                // チャート描画
                const canvas = document.getElementById('spider-chart');
                const emptyMsg = document.getElementById('chart-empty');
                if (data.players && data.players.length > 0) {
                    AmisterChart.renderChart(canvas, data);
                    emptyMsg.style.display = 'none';
                } else {
                    canvas.style.display = 'none';
                    emptyMsg.style.display = '';
                }

                // テーブル描画
                renderTable(data);
            })
            .catch(err => {
                document.getElementById('stats-table-wrapper').innerHTML =
                    '<p class="flash error">データ取得エラー</p>';
                console.error(err);
            });

        function renderTable(data) {
            const wrapper = document.getElementById('stats-table-wrapper');
            if (!data.players || data.players.length === 0) {
                wrapper.innerHTML = '<p class="text-muted text-center" style="font-size:.85rem;">データなし</p>';
                return;
            }

            // 選手を合計ポイント降順でソート
            const sorted = [...data.players].sort((a, b) => b.total_points - a.total_points);

            let html = '<table class="stats-table"><thead><tr>'
                + '<th>選手</th><th>合計pt</th>';

            // 行為列ヘッダー
            data.actions.forEach(a => {
                html += `<th title="${escHtml(a.name)}">${escHtml(a.name)}</th>`;
            });
            html += '</tr></thead><tbody>';

            sorted.forEach((player, rank) => {
                const medal = rank === 0 ? '&#127945;' : rank === 1 ? '&#129352;' : rank === 2 ? '&#129353;' : '';
                html += `<tr><td>${medal} #${player.number} ${escHtml(player.name)}</td>`;
                html += `<td><strong>${player.total_points}</strong></td>`;
                data.actions.forEach(a => {
                    const entry = player.actions[a.id];
                    html += `<td>${entry ? entry.count : 0}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            wrapper.innerHTML = html;
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    })();
    </script>
</body>
</html>
