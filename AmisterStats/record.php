<?php
// 記録画面 — 試合・選手・行為を選んでポイントをログに記録する
require_once __DIR__ . '/db/connect.php';

try {
    $pdo = get_pdo();
    $matches = $pdo->query(
        'SELECT id, match_date, opponent, location FROM matches ORDER BY match_date DESC, id DESC'
    )->fetchAll();
    $players = $pdo->query(
        'SELECT id, name, number FROM players ORDER BY number ASC, name ASC'
    )->fetchAll();
    $actions = $pdo->query(
        'SELECT id, name, point_value, category FROM actions ORDER BY category DESC, id ASC'
    )->fetchAll();
    $db_ok = true;
} catch (Exception $e) {
    $db_ok = false;
    $db_error = $e->getMessage();
}

$selected_match_id = $_GET['match_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a7f3c">
    <title>記録する — AmisterStats</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-header">
        <a class="back-btn" href="index.php" aria-label="ホームへ戻る">&#8592;</a>
        <h1>&#128221; 記録する</h1>
    </header>

    <main class="container">
        <?php if (!$db_ok): ?>
            <div class="flash error">
                DB接続エラー: <?= htmlspecialchars($db_error) ?>
            </div>
        <?php else: ?>

        <!-- 試合選択 -->
        <div class="card">
            <div class="card-title">試合を選択</div>
            <?php if (empty($matches)): ?>
                <p class="text-muted" style="font-size:.9rem;">
                    試合が登録されていません。
                    <a href="register.php#match">マスター登録</a>から追加してください。
                </p>
            <?php else: ?>
                <div class="form-group mb-1">
                    <select id="match-select">
                        <option value="">-- 試合を選んでください --</option>
                        <?php foreach ($matches as $m): ?>
                            <option value="<?= $m['id'] ?>"
                                <?= $selected_match_id == $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['match_date']) ?>
                                vs <?= htmlspecialchars($m['opponent']) ?>
                                <?= $m['location'] ? '@ ' . htmlspecialchars($m['location']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <!-- 選手選択 -->
        <div class="card">
            <div class="card-title">選手を選択</div>
            <?php if (empty($players)): ?>
                <p class="text-muted" style="font-size:.9rem;">
                    選手が登録されていません。
                    <a href="register.php#player">マスター登録</a>から追加してください。
                </p>
            <?php else: ?>
                <div class="player-grid" id="player-grid">
                    <?php foreach ($players as $p): ?>
                        <button
                            class="player-btn"
                            data-player-id="<?= $p['id'] ?>"
                            data-player-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                            data-player-number="<?= (int)$p['number'] ?>"
                            type="button">
                            <span class="pnum"><?= (int)$p['number'] ?></span>
                            <?= htmlspecialchars($p['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 行為ボタン（選手・試合が選択されたとき表示） -->
        <div class="card" id="action-section" style="display:none;">
            <div class="flex items-center justify-between mb-1">
                <div class="card-title mb-1" style="margin-bottom:0;">
                    行為を記録
                    <span style="font-weight:400;color:#555;margin-left:.4rem;">
                        &rarr; <span id="selected-player-label">未選択</span>
                    </span>
                </div>
                <button class="btn btn-danger" id="undo-btn" type="button" style="font-size:.8rem;padding:.45rem .8rem;">
                    ↩ 取り消し
                </button>
            </div>

            <?php if (empty($actions)): ?>
                <p class="text-muted" style="font-size:.9rem;">
                    行為が登録されていません。
                    <a href="register.php#action">マスター登録</a>から追加してください。
                </p>
            <?php else: ?>
                <div class="action-grid" id="action-grid">
                    <?php foreach ($actions as $a): ?>
                        <button
                            class="action-btn <?= htmlspecialchars($a['category']) ?>"
                            data-action-id="<?= $a['id'] ?>"
                            data-action-name="<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>"
                            data-point-value="<?= (int)$a['point_value'] ?>"
                            type="button">
                            <?= htmlspecialchars($a['name']) ?>
                            <span class="pts">
                                <?= $a['point_value'] >= 0 ? '+' : '' ?><?= (int)$a['point_value'] ?>pt
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ログフィード -->
        <div class="card">
            <div class="card-title">記録ログ（直近）</div>
            <div class="log-feed" id="log-feed">
                <p class="text-muted text-center" style="font-size:.85rem;padding:.5rem;">
                    記録するとここに表示されます
                </p>
            </div>
        </div>

        <?php endif; ?>
    </main>

    <div id="toast"></div>
    <script src="js/app.js"></script>
</body>
</html>
