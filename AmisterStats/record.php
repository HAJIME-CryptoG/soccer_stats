<?php
/**
 * record.php — アミスター・スタッツ 記録入力画面
 */
require_once __DIR__ . '/db/connect.php';

/* ---- DB: 試合リスト取得 + match_id カラムのマイグレーション ---- */
$matches = [];
try {
    $pdo = get_pdo();

    // play_logs に match_id カラムが存在しなければ自動追加
    $cols = $pdo->query("SHOW COLUMNS FROM play_logs LIKE 'match_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE play_logs ADD COLUMN match_id INT UNSIGNED NULL DEFAULT NULL");
    }

    $matches = $pdo->query(
        'SELECT id, match_date, opponent, match_type, tournament_name FROM matches ORDER BY match_date DESC, id DESC'
    )->fetchAll();
} catch (Exception $e) {
    // DB 接続失敗は無視（試合リストなしで動作）
}

/* ---- 選手リスト: DBから取得（登録なしの場合は空） ---- */
$players = [];
try {
    if (!isset($pdo)) $pdo = get_pdo();
    $players = $pdo->query(
        'SELECT name FROM players ORDER BY number ASC, name ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $players = [];
}

/**
 * OF行為: type は 'success'(青) / 'fail'(赤) / 'special'(橙)
 */
$of_actions = [
    ['name' => 'パス成功',     'type' => 'success'],
    ['name' => 'パス失敗',     'type' => 'fail'],
    ['name' => 'キーパス',     'type' => 'special'],
    ['name' => 'アシスト',     'type' => 'special'],
    ['name' => 'センタリング', 'type' => 'success'],
    ['name' => 'シュート内',   'type' => 'success'],
    ['name' => 'シュート外',   'type' => 'fail'],
    ['name' => 'ドリブル成功', 'type' => 'success'],
    ['name' => 'ドリブル失敗', 'type' => 'fail'],
    ['name' => 'トラップ成功', 'type' => 'success'],
    ['name' => 'トラップ失敗', 'type' => 'fail'],
    ['name' => 'オフサイド',   'type' => 'fail'],
];

/**
 * DF行為: type は 'success'(青) / 'negative'(赤) / 'special'(黄)
 */
$df_actions = [
    ['name' => 'ボール奪取',       'type' => 'success'],
    ['name' => 'インターセプト',   'type' => 'success'],
    ['name' => 'シュートブロック', 'type' => 'success'],
    ['name' => 'クリア(味方)',     'type' => 'success'],
    ['name' => 'セービング',       'type' => 'success'],
    ['name' => 'ブレイクアウェイ', 'type' => 'success'],
    ['name' => 'パス成功/SK',      'type' => 'success'],
    ['name' => 'カバーリング',     'type' => 'success'],
    ['name' => 'スプリント20m',    'type' => 'special'],
    ['name' => 'クリア(相手)',     'type' => 'negative'],
    ['name' => 'クリア(外)',       'type' => 'negative'],
];

/* ---- ヘルパー ---- */
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1 で iOS でのダブルタップズーム防止 -->
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1565c0">
    <!-- PWA: ホーム画面追加時にフルスクリーン -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>記録 — アミスター・スタッツ</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="record-layout">

<!-- ================================================================
     フェーズヘッダー（上部タブ）
     ================================================================ -->
<header class="phase-header" id="phaseHeader" data-phase="offense">

    <a class="back-btn-sm" href="index.php" aria-label="ホームへ戻る">&#8592;</a>

    <div class="phase-tabs" role="tablist">
        <button class="phase-tab of-tab active"
                id="tabOf"
                role="tab"
                aria-selected="true"
                onclick="switchPhase('offense')">
            🔵&nbsp;オフェンス
        </button>
        <button class="phase-tab df-tab"
                id="tabDf"
                role="tab"
                aria-selected="false"
                onclick="switchPhase('defense')">
            🔴&nbsp;ディフェンス
        </button>
    </div>

    <span class="clock" id="clock" aria-live="off"></span>
    <button class="hamburger-btn" onclick="openNav()" aria-label="メニューを開く" style="margin-left:0;">
        <span></span><span></span><span></span>
    </button>
</header>

<!-- ================================================================
     試合選択バー
     ================================================================ -->
<div class="match-bar">
    <label for="matchSelect">🏟️</label>
    <select id="matchSelect" onchange="onMatchChange(this.value)">
        <option value="">▼ 試合を選択してください</option>
        <?php foreach ($matches as $m): ?>
        <option value="<?= esc((string)$m['id']) ?>">
            <?php
                $type_label = ($m['match_type'] ?? 'friendly') === 'official' ? '🏆' : '🤝';
                $tournament = !empty($m['tournament_name']) ? ' [' . $m['tournament_name'] . ']' : '';
                echo $type_label . esc($m['match_date']) . $tournament . ' vs ' . esc($m['opponent']);
            ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- ================================================================
     スプリットビュー：左=選手 / 右=行為
     ================================================================ -->
<div class="split-view">

    <!-- 左パネル：選手ボタン 2列×6行 -->
    <section class="player-panel" aria-label="選手選択">
        <div class="panel-label">選手</div>
        <div class="player-grid-v2" id="playerGrid">
            <?php if (empty($players)): ?>
            <div style="grid-column:1/-1;color:rgba(255,255,255,.45);font-size:.75rem;text-align:center;padding:.5rem 0;">
                選手未登録<br>マスター登録から追加してください
            </div>
            <?php else: ?>
            <?php foreach ($players as $name): ?>
            <button class="player-btn-v2"
                    type="button"
                    data-name="<?= esc($name) ?>"
                    onclick="selectPlayer(this, '<?= esc($name) ?>')">
                <?= esc($name) ?>
            </button>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- 右パネル：プレーボタン（スクロール可） -->
    <section class="action-panel" id="actionPanel" aria-label="プレー選択">

        <!-- オフェンス行為 -->
        <div id="ofActions">
            <div class="action-section-label success-label">✅ 成功 / プレー</div>
            <div class="action-grid-v2">
                <?php foreach ($of_actions as $a): if ($a['type'] !== 'fail'): ?>
                <button class="action-btn-v2 of-<?= esc($a['type']) ?>"
                        type="button"
                        data-action="<?= esc($a['name']) ?>"
                        onclick="toggleAction(this, '<?= esc($a['name']) ?>')">
                    <?= esc($a['name']) ?>
                </button>
                <?php endif; endforeach; ?>
            </div>

            <div class="action-section-label fail-label">❌ 失敗 / マイナス</div>
            <div class="action-grid-v2">
                <?php foreach ($of_actions as $a): if ($a['type'] === 'fail'): ?>
                <button class="action-btn-v2 of-fail"
                        type="button"
                        data-action="<?= esc($a['name']) ?>"
                        onclick="toggleAction(this, '<?= esc($a['name']) ?>')">
                    <?= esc($a['name']) ?>
                </button>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <!-- ディフェンス行為（初期非表示） -->
        <div id="dfActions" style="display:none;">
            <div class="action-section-label success-label">✅ 成功</div>
            <div class="action-grid-v2">
                <?php foreach ($df_actions as $a): if ($a['type'] === 'success'): ?>
                <button class="action-btn-v2 df-success"
                        type="button"
                        data-action="<?= esc($a['name']) ?>"
                        onclick="toggleAction(this, '<?= esc($a['name']) ?>')">
                    <?= esc($a['name']) ?>
                </button>
                <?php endif; endforeach; ?>
            </div>

            <div class="action-section-label special-label">⚡ スプリント / 特殊</div>
            <div class="action-grid-v2">
                <?php foreach ($df_actions as $a): if ($a['type'] === 'special'): ?>
                <button class="action-btn-v2 df-special"
                        type="button"
                        data-action="<?= esc($a['name']) ?>"
                        onclick="toggleAction(this, '<?= esc($a['name']) ?>')">
                    <?= esc($a['name']) ?>
                </button>
                <?php endif; endforeach; ?>
            </div>

            <div class="action-section-label fail-label">❌ 失点 / ミス</div>
            <div class="action-grid-v2">
                <?php foreach ($df_actions as $a): if ($a['type'] === 'negative'): ?>
                <button class="action-btn-v2 df-negative"
                        type="button"
                        data-action="<?= esc($a['name']) ?>"
                        onclick="toggleAction(this, '<?= esc($a['name']) ?>')">
                    <?= esc($a['name']) ?>
                </button>
                <?php endif; endforeach; ?>
            </div>
        </div>

    </section><!-- /action-panel -->
</div><!-- /split-view -->

<!-- ================================================================
     フッター：ステータス / 登録ボタン / Undoボタン
     ================================================================ -->
<footer class="record-footer">
    <div class="status-bar" id="statusBar" aria-live="polite">
        選手を選択 → プレーをタップ → 登録
    </div>
    <div class="footer-buttons">
        <button class="register-btn" id="registerBtn"
                type="button" onclick="register()" disabled>
            ✅ 登録
        </button>
        <button class="undo-btn" id="undoBtn"
                type="button" onclick="undo()" disabled>
            ↩ Undo
        </button>
    </div>
</footer>

<!-- トースト通知 -->
<div id="toast" role="status" aria-live="assertive"></div>

<!-- ================================================================
     JavaScript — 状態管理・API通信
     ================================================================ -->
<script>
(function () {
'use strict';

/* ---------- アプリ状態 ---------- */
const state = {
    phase:           'offense',  // 'offense' | 'defense'
    selectedPlayer:  null,       // 選択中の選手名
    selectedActions: [],         // 選択中の行為名リスト
    lastBatchId:     null,       // 直前バッチID（Undo用）
    matchId:         null,       // 選択中の試合ID
};

/* ---------- 試合選択（LocalStorageで保持） ---------- */
window.onMatchChange = function(val) {
    state.matchId = val || null;
    if (state.matchId) {
        localStorage.setItem('amister_match_id', state.matchId);
    } else {
        localStorage.removeItem('amister_match_id');
    }
    updateMatchBarStyle();
};

function updateMatchBarStyle() {
    const sel = document.getElementById('matchSelect');
    if (!sel) return;
    sel.style.borderColor = state.matchId ? '#4fa3ff' : '#e53935';
    sel.style.background  = state.matchId ? '#2a2f45' : '#3a1f1f';
}

// ページロード時: LocalStorageから試合IDを復元
(function restoreMatch() {
    const saved = localStorage.getItem('amister_match_id');
    if (!saved) { updateMatchBarStyle(); return; }
    const sel = document.getElementById('matchSelect');
    if (!sel) return;
    const opt = sel.querySelector(`option[value="${saved}"]`);
    if (opt) {
        sel.value     = saved;
        state.matchId = saved;
    } else {
        localStorage.removeItem('amister_match_id');
    }
    updateMatchBarStyle();
})();

/* ---------- 時計 ---------- */
(function tick() {
    const el = document.getElementById('clock');
    if (el) {
        el.textContent = new Date().toLocaleTimeString('ja-JP', {
            hour: '2-digit', minute: '2-digit'
        });
    }
    setTimeout(tick, 10_000);
})();

/* ---------- フェーズ切り替え ---------- */
window.switchPhase = function (phase) {
    state.phase = phase;
    clearActionSelections();

    const tabOf  = document.getElementById('tabOf');
    const tabDf  = document.getElementById('tabDf');
    const ofSec  = document.getElementById('ofActions');
    const dfSec  = document.getElementById('dfActions');
    const header = document.getElementById('phaseHeader');

    if (phase === 'offense') {
        tabOf.classList.add('active');    tabOf.setAttribute('aria-selected', 'true');
        tabDf.classList.remove('active'); tabDf.setAttribute('aria-selected', 'false');
        ofSec.style.display = '';
        dfSec.style.display = 'none';
    } else {
        tabDf.classList.add('active');    tabDf.setAttribute('aria-selected', 'true');
        tabOf.classList.remove('active'); tabOf.setAttribute('aria-selected', 'false');
        dfSec.style.display = '';
        ofSec.style.display = 'none';
    }
    header.dataset.phase = phase;

    // 行為パネルをトップへスクロール
    document.getElementById('actionPanel').scrollTop = 0;

    updateStatus();
    updateRegisterBtn();
};

/* ---------- 選手選択 ---------- */
window.selectPlayer = function (btn, name) {
    const isSame = (state.selectedPlayer === name);

    // 全ボタンの選択解除
    document.querySelectorAll('.player-btn-v2').forEach(b => b.classList.remove('active'));

    if (isSame) {
        state.selectedPlayer = null;
    } else {
        state.selectedPlayer = name;
        btn.classList.add('active');
    }

    updateStatus();
    updateRegisterBtn();
};

/* ---------- 行為トグル ---------- */
window.toggleAction = function (btn, action) {
    const on = (btn.dataset.selected === '1');
    if (on) {
        btn.dataset.selected = '';
        btn.classList.remove('selected');
        state.selectedActions = state.selectedActions.filter(a => a !== action);
    } else {
        btn.dataset.selected = '1';
        btn.classList.add('selected');
        state.selectedActions.push(action);
    }
    updateStatus();
    updateRegisterBtn();
};

/* ---------- 行為選択リセット ---------- */
function clearActionSelections() {
    state.selectedActions = [];
    document.querySelectorAll('.action-btn-v2').forEach(b => {
        b.dataset.selected = '';
        b.classList.remove('selected');
    });
}

/* ---------- ステータス表示更新 ---------- */
function updateStatus() {
    const el = document.getElementById('statusBar');
    if (!state.selectedPlayer) {
        el.textContent = '選手を選択 → プレーをタップ → 登録';
        el.className = 'status-bar';
    } else if (state.selectedActions.length === 0) {
        el.textContent = `👤 ${state.selectedPlayer} — プレーを選択してください`;
        el.className = 'status-bar status-player';
    } else {
        el.textContent =
            `👤 ${state.selectedPlayer}：${state.selectedActions.join(' / ')}`;
        el.className = 'status-bar status-ready';
    }
}

/* ---------- 登録ボタン活性制御 ---------- */
function updateRegisterBtn() {
    const btn = document.getElementById('registerBtn');
    btn.disabled = !(state.selectedPlayer && state.selectedActions.length > 0);
}

/* ---------- 登録（一括保存）---------- */
window.register = async function () {
    if (!state.selectedPlayer || state.selectedActions.length === 0) return;

    // 試合未選択の場合は警告（登録は続行するが試合と紐づかない）
    if (!state.matchId) {
        showToast('⚠ 試合未選択。上のセレクトで試合を選ぶと集計で試合別に見られます');
    }

    const registerBtn = document.getElementById('registerBtn');
    const undoBtn     = document.getElementById('undoBtn');
    registerBtn.disabled = true;
    registerBtn.textContent = '保存中…';

    // バッチIDは「タイムスタンプ + ランダム文字列」で一意性確保
    const batchId = Date.now() + '-' + Math.random().toString(36).slice(2, 9);

    const body = new FormData();
    body.append('action',   'save');
    body.append('player',   state.selectedPlayer);
    body.append('phase',    state.phase);
    body.append('batch_id', batchId);
    if (state.matchId) body.append('match_id', state.matchId);
    state.selectedActions.forEach(a => body.append('acts[]', a));

    try {
        const res  = await fetch('api/save_log.php', { method: 'POST', body });
        const data = await res.json();

        if (data.success) {
            state.lastBatchId    = batchId;
            undoBtn.disabled     = false;

            const label = state.selectedActions.join(' / ');
            showToast(`✅ ${state.selectedPlayer}：${label} を保存しました`);

            clearActionSelections();
        } else {
            showToast('❌ 保存失敗: ' + (data.message ?? '不明なエラー'));
        }
    } catch {
        showToast('❌ 通信エラーが発生しました');
    }

    registerBtn.textContent = '✅ 登録';
    updateRegisterBtn();
    updateStatus();
};

/* ---------- Undo（直前バッチ削除）---------- */
window.undo = async function () {
    if (!state.lastBatchId) return;

    const undoBtn = document.getElementById('undoBtn');
    undoBtn.disabled    = true;
    undoBtn.textContent = '…';

    const body = new FormData();
    body.append('action',   'undo');
    body.append('batch_id', state.lastBatchId);

    try {
        const res  = await fetch('api/save_log.php', { method: 'POST', body });
        const data = await res.json();

        if (data.success) {
            state.lastBatchId = null;
            showToast(`↩ 直前の登録（${data.deleted}件）を取り消しました`);
        } else {
            undoBtn.disabled = false;
            showToast('❌ Undo失敗: ' + (data.message ?? '不明なエラー'));
        }
    } catch {
        undoBtn.disabled = false;
        showToast('❌ 通信エラーが発生しました');
    }

    undoBtn.textContent = '↩ Undo';
};

/* ---------- トースト通知 ---------- */
let toastTimer;
function showToast(message) {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2800);
}

})(); // IIFE end
</script>
<?php $nav_current = 'record'; require __DIR__ . '/partials/nav_drawer.php'; ?>
</body>
</html>
