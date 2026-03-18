<?php
// 集計画面 — マトリックス表 + スパイダーチャート
require_once __DIR__ . '/db/connect.php';

try {
    $pdo = get_pdo();
    $matches = $pdo->query(
        'SELECT * FROM matches ORDER BY match_date DESC, id DESC'
    )->fetchAll();
    // match_type / tournament_name カラムが未追加の既存DBでも動くようデフォルト保証
    foreach ($matches as &$m) {
        $m['match_type']      = $m['match_type']      ?? 'friendly';
        $m['tournament_name'] = $m['tournament_name'] ?? '';
    }
    unset($m);
    // 選手リストを players テーブルから取得（登録済み全選手を表示）
    $player_names = $pdo->query(
        'SELECT name FROM players ORDER BY number ASC, name ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
    $db_ok = true;
} catch (Exception $e) {
    $db_ok    = false;
    $db_error = $e->getMessage();
}

$filter_match_id   = $_GET['match_id']    ?? '';
$filter_player_name = $_GET['player_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1565c0">
    <title>集計を見る — AmisterStats</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* マトリックス表タブ */
        .matrix-tabs {
            display: flex;
            gap: .5rem;
            margin-bottom: .8rem;
        }
        .matrix-tab {
            flex: 1;
            padding: .5rem;
            border: none;
            border-radius: 6px;
            background: #e0e0e0;
            color: #555;
            font-weight: 700;
            font-size: .85rem;
            cursor: pointer;
            font-family: var(--font);
            transition: background .15s, color .15s;
        }
        .matrix-tab.active {
            background: #1565c0;
            color: #fff;
        }
        .matrix-tab.df-active {
            background: #b71c1c;
            color: #fff;
        }
        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .matrix-table {
            border-collapse: collapse;
            font-size: .8rem;
            min-width: 100%;
        }
        .matrix-table th,
        .matrix-table td {
            border: 1px solid #ddd;
            padding: .3rem .4rem;
            text-align: center;
            white-space: nowrap;
        }
        .matrix-table th {
            background: #f0f4ff;
            font-weight: 700;
        }
        .matrix-table td:first-child {
            text-align: left;
            font-weight: 600;
            background: #fafafa;
            position: sticky;
            left: 0;
        }
        .matrix-table td.zero { color: #ccc; }
        .matrix-table td.has-val { color: #1565c0; font-weight: 700; }
    </style>
</head>
<body>
    <header class="app-header" style="background:#1565c0;">
        <a class="back-btn" href="index.php" aria-label="ホームへ戻る">&#8592;</a>
        <h1>&#128202; 集計を見る</h1>
        <button class="hamburger-btn" onclick="openNav()" aria-label="メニューを開く">
            <span></span><span></span><span></span>
        </button>
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
                                <?php
                                    $type_label = ($m['match_type'] ?? 'friendly') === 'official' ? '🏆' : '🤝';
                                    $tournament = !empty($m['tournament_name']) ? ' [' . htmlspecialchars($m['tournament_name']) . ']' : '';
                                    echo $type_label . htmlspecialchars($m['match_date']) . $tournament . ' vs ' . htmlspecialchars($m['opponent']);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="f-player">選手</label>
                    <select id="f-player" name="player_name">
                        <option value="">全選手</option>
                        <?php foreach ($player_names as $pname): ?>
                            <option value="<?= htmlspecialchars($pname) ?>"
                                <?= $filter_player_name === $pname ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pname) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary btn-block" type="submit">集計を更新</button>
            </form>
        </div>

        <!-- 集計表（チャートの前） -->
        <div class="card">
            <div class="card-title">集計表</div>
            <div id="matrix-wrapper">
                <div class="spinner"></div>
            </div>
        </div>

        <!-- スパイダーチャート -->
        <div class="card">
            <div class="card-title">スパイダーチャート</div>
            <!-- OF/DF タブ -->
            <div style="display:flex;gap:.5rem;margin-bottom:.8rem;">
                <button id="chart-tab-of" class="matrix-tab active"
                        onclick="switchChartPhase('offense')" type="button">
                    🔵 オフェンス
                </button>
                <button id="chart-tab-df" class="matrix-tab"
                        onclick="switchChartPhase('defense')" type="button">
                    🔴 ディフェンス
                </button>
            </div>
            <!-- OFチャート -->
            <div id="chart-of-wrapper" class="chart-wrapper">
                <canvas id="spider-chart-of"></canvas>
            </div>
            <p id="chart-of-empty" class="text-muted text-center mt-2" style="font-size:.85rem;display:none;">
                表示するデータがありません
            </p>
            <!-- DFチャート（初期非表示） -->
            <div id="chart-df-wrapper" class="chart-wrapper" style="display:none;">
                <canvas id="spider-chart-df"></canvas>
            </div>
            <p id="chart-df-empty" class="text-muted text-center mt-2" style="font-size:.85rem;display:none;">
                表示するデータがありません
            </p>
        </div>

        <?php endif; ?>
    </main>

    <div id="toast"></div>
    <script src="js/chart_config.js"></script>
    <script>
    (function () {
        const matchId    = <?= json_encode($filter_match_id    ?: null) ?>;
        const playerName = <?= json_encode($filter_player_name ?: null) ?>;

        const params = new URLSearchParams();
        if (matchId)    params.set('match_id',    matchId);
        if (playerName) params.set('player_name', playerName);

        const apiUrl = 'api/get_stats.php' + (params.toString() ? '?' + params.toString() : '');

        let globalData = null;

        window.switchChartPhase = function(phase) {
            document.getElementById('chart-tab-of').className =
                'matrix-tab' + (phase === 'offense' ? ' active' : '');
            document.getElementById('chart-tab-df').className =
                'matrix-tab' + (phase === 'defense' ? ' df-active' : '');
            // OFとDFのcanvasラッパーを切り替える
            document.getElementById('chart-of-wrapper').style.display = phase === 'offense' ? '' : 'none';
            document.getElementById('chart-of-empty').style.display =
                (phase === 'offense' && globalData && !hasChartData(globalData, 'offense')) ? '' : 'none';
            document.getElementById('chart-df-wrapper').style.display = phase === 'defense' ? '' : 'none';
            document.getElementById('chart-df-empty').style.display =
                (phase === 'defense' && globalData && !hasChartData(globalData, 'defense')) ? '' : 'none';
        };

        function hasChartData(data, phase) {
            return data.players && data.players.some(p =>
                phase === 'offense'
                    ? (p.of_chart_data || []).some(v => v > 0)
                    : (p.df_chart_data || []).some(v => v > 0)
            );
        }

        function drawBothCharts(data) {
            // オフェンスチャート
            const ofCanvas  = document.getElementById('spider-chart-of');
            const ofEmpty   = document.getElementById('chart-of-empty');
            if (hasChartData(data, 'offense')) {
                ofCanvas.style.display = '';
                ofEmpty.style.display  = 'none';
                AmisterChart.renderChart(ofCanvas, data, null, 'offense');
            } else {
                ofCanvas.style.display = 'none';
                ofEmpty.style.display  = '';
            }
            // ディフェンスチャート
            const dfCanvas  = document.getElementById('spider-chart-df');
            const dfEmpty   = document.getElementById('chart-df-empty');
            if (hasChartData(data, 'defense')) {
                dfCanvas.style.display = '';
                dfEmpty.style.display  = 'none';
                AmisterChart.renderChart(dfCanvas, data, null, 'defense');
            } else {
                dfCanvas.style.display = 'none';
                dfEmpty.style.display  = '';
            }
        }

        fetch(apiUrl)
            .then(r => r.json())
            .then(data => {
                globalData = data;
                renderMatrix(data);
                drawBothCharts(data);
            })
            .catch(err => {
                document.getElementById('matrix-wrapper').innerHTML =
                    '<p class="flash error">データ取得エラー</p>';
                console.error(err);
            });

        /* ---- マトリックス表の描画 ---- */
        function renderMatrix(data) {
            const wrapper  = document.getElementById('matrix-wrapper');
            const players  = data.players  || [];
            const ofActions = data.of_actions || [];
            const dfActions = data.df_actions || [];

            if (players.length === 0) {
                wrapper.innerHTML = '<p class="text-muted text-center" style="font-size:.85rem;">データなし</p>';
                return;
            }

            let html = `
                <div class="matrix-tabs">
                    <button class="matrix-tab active" id="tab-of"
                            onclick="switchMatrix('offense')">🔵 オフェンス</button>
                    <button class="matrix-tab" id="tab-df"
                            onclick="switchMatrix('defense')">🔴 ディフェンス</button>
                </div>
            `;

            html += buildTable('matrix-offense', players, ofActions, 'offense', '');
            html += buildTable('matrix-defense', players, dfActions, 'defense', 'display:none');

            wrapper.innerHTML = html;
        }

        function buildTable(id, players, actions, phase, style) {
            let h = `<div id="${id}" style="${style}"><div class="table-scroll">`;
            h += '<table class="matrix-table"><thead><tr><th>選手</th>';
            actions.forEach(a => { h += `<th>${escHtml(a)}</th>`; });
            h += '</tr></thead><tbody>';
            players.forEach(p => {
                h += `<tr><td>${escHtml(p.name)}</td>`;
                actions.forEach(a => {
                    const cnt = p[phase] && p[phase][a] ? p[phase][a] : 0;
                    const cls = cnt > 0 ? 'has-val' : 'zero';
                    h += `<td class="${cls}">${cnt > 0 ? cnt : '–'}</td>`;
                });
                h += '</tr>';
            });
            h += '</tbody></table></div></div>';
            return h;
        }

        window.switchMatrix = function(phase) {
            document.getElementById('matrix-offense').style.display = phase === 'offense' ? '' : 'none';
            document.getElementById('matrix-defense').style.display = phase === 'defense' ? '' : 'none';
            const tabOf = document.getElementById('tab-of');
            const tabDf = document.getElementById('tab-df');
            tabOf.className = 'matrix-tab' + (phase === 'offense' ? ' active' : '');
            tabDf.className = 'matrix-tab' + (phase === 'defense' ? ' df-active' : '');
        };

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    })();
    </script>
    <?php $nav_current = 'view'; require __DIR__ . '/partials/nav_drawer.php'; ?>
</body>
</html>
