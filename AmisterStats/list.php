<?php
// 一覧ページ — 登録済み選手・試合を表示
require_once __DIR__ . '/db/connect.php';

try {
    $pdo     = get_pdo();
    $players = $pdo->query('SELECT * FROM players ORDER BY number ASC, name ASC')->fetchAll();
    $matches = $pdo->query(
        'SELECT * FROM matches ORDER BY match_date DESC, id DESC'
    )->fetchAll();
    $db_ok = true;
} catch (Exception $e) {
    $db_ok    = false;
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#37474f">
    <title>一覧 — AmisterStats</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-header" style="background:#37474f;">
        <a class="back-btn" href="index.php" aria-label="ホームへ戻る">&#8592;</a>
        <h1>&#128203; 一覧</h1>
        <button class="hamburger-btn" onclick="openNav()" aria-label="メニューを開く">
            <span></span><span></span><span></span>
        </button>
    </header>

    <main class="container">
        <?php if (!$db_ok): ?>
            <div class="flash error">DB接続エラー: <?= htmlspecialchars($db_error) ?></div>
        <?php else: ?>

        <!-- タブ切り替え -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="players" type="button">選手</button>
            <button class="tab-btn" data-tab="matches" type="button">試合</button>
        </div>

        <!-- === 選手一覧タブ === -->
        <div class="tab-content active" id="tab-players">
            <div class="card">
                <div class="card-title">登録済み選手 (<?= count($players) ?>人)</div>
                <?php if (empty($players)): ?>
                    <p class="text-muted" style="font-size:.85rem;">まだ登録されていません</p>
                <?php else: ?>
                    <table class="stats-table">
                        <thead>
                            <tr><th>#</th><th>氏名</th><th>チーム</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $p): ?>
                                <tr>
                                    <td><?= (int)$p['number'] ?></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['team']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- === 試合一覧タブ === -->
        <div class="tab-content" id="tab-matches">
            <div class="card">
                <div class="card-title">登録済み試合 (<?= count($matches) ?>件)</div>
                <?php if (empty($matches)): ?>
                    <p class="text-muted" style="font-size:.85rem;">まだ登録されていません</p>
                <?php else: ?>
                    <?php foreach ($matches as $m): ?>
                        <?php
                            $type    = $m['match_type'] ?? 'friendly';
                            $icon    = $type === 'official' ? '🏆' : '🤝';
                            $typeStr = $type === 'official' ? '公式試合' : '練習試合';
                        ?>
                        <div style="border:1px solid var(--color-border);border-radius:8px;padding:.75rem;margin-bottom:.75rem;">
                            <div style="font-weight:700;font-size:.95rem;margin-bottom:.3rem;">
                                <?= $icon ?> <?= htmlspecialchars($m['match_date']) ?>
                                vs <?= htmlspecialchars($m['opponent']) ?>
                            </div>
                            <div style="font-size:.82rem;color:var(--color-muted);display:flex;gap:1rem;flex-wrap:wrap;">
                                <span><?= $typeStr ?></span>
                                <?php if (!empty($m['tournament_name'])): ?>
                                    <span>大会: <?= htmlspecialchars($m['tournament_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($m['location'])): ?>
                                    <span>📍 <?= htmlspecialchars($m['location']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </main>

    <script>
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('tab-' + target).classList.add('active');
        });
    });
    </script>
    <?php $nav_current = 'list'; require __DIR__ . '/partials/nav_drawer.php'; ?>
</body>
</html>
