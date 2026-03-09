<?php
// マスター登録画面 — 選手・行為・試合を登録する
require_once __DIR__ . '/db/connect.php';

$flash = [];

// ---- POST処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';

    try {
        $pdo = get_pdo();

        if ($type === 'player') {
            $name   = trim($_POST['player_name']   ?? '');
            $number = (int)($_POST['player_number'] ?? 0);
            $team   = trim($_POST['player_team']   ?? '');

            if ($name === '') {
                $flash[] = ['error', '選手名を入力してください'];
            } else {
                $pdo->prepare(
                    'INSERT INTO players (name, number, team) VALUES (?, ?, ?)'
                )->execute([$name, $number, $team]);
                $flash[] = ['success', "選手「{$name}」を登録しました"];
            }

        } elseif ($type === 'action') {
            $name   = trim($_POST['action_name']  ?? '');
            $points = (int)($_POST['action_points'] ?? 1);
            $cat    = $points >= 0 ? 'positive' : 'negative';

            if ($name === '') {
                $flash[] = ['error', '行為名を入力してください'];
            } else {
                $pdo->prepare(
                    'INSERT INTO actions (name, point_value, category) VALUES (?, ?, ?)'
                )->execute([$name, $points, $cat]);
                $flash[] = ['success', "行為「{$name}」({$points}pt) を登録しました"];
            }

        } elseif ($type === 'match') {
            $date     = $_POST['match_date']     ?? '';
            $opponent = trim($_POST['opponent']  ?? '');
            $location = trim($_POST['location']  ?? '');

            if (!$date || !$opponent) {
                $flash[] = ['error', '日付と対戦相手を入力してください'];
            } else {
                $pdo->prepare(
                    'INSERT INTO matches (match_date, opponent, location) VALUES (?, ?, ?)'
                )->execute([$date, $opponent, $location]);
                $flash[] = ['success', "{$date} vs {$opponent} を登録しました"];
            }
        }

    } catch (Exception $e) {
        $flash[] = ['error', 'DBエラー: ' . $e->getMessage()];
    }
}

// ---- 一覧取得 ----
try {
    $pdo     = get_pdo();
    $players = $pdo->query('SELECT * FROM players ORDER BY number ASC, name ASC')->fetchAll();
    $actions = $pdo->query('SELECT * FROM actions ORDER BY category DESC, id ASC')->fetchAll();
    $matches = $pdo->query('SELECT * FROM matches ORDER BY match_date DESC, id DESC')->fetchAll();
    $db_ok   = true;
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
    <meta name="theme-color" content="#6a1b9a">
    <title>マスター登録 — AmisterStats</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-header" style="background:#6a1b9a;">
        <a class="back-btn" href="index.php" aria-label="ホームへ戻る">&#8592;</a>
        <h1>&#9881;&#65039; マスター登録</h1>
    </header>

    <main class="container">

        <?php foreach ($flash as [$type, $msg]): ?>
            <div class="flash <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php if (!$db_ok): ?>
            <div class="flash error">DB接続エラー: <?= htmlspecialchars($db_error) ?></div>
        <?php else: ?>

        <!-- タブ切り替え -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="player" type="button">選手</button>
            <button class="tab-btn" data-tab="action" type="button">行為</button>
            <button class="tab-btn" data-tab="match" type="button">試合</button>
        </div>

        <!-- === 選手タブ === -->
        <div class="tab-content active" id="tab-player">
            <div class="card" id="player">
                <div class="card-title">選手を登録</div>
                <form method="post" action="register.php#player">
                    <input type="hidden" name="type" value="player">
                    <div class="form-group">
                        <label for="player_name">選手名 <span style="color:red">*</span></label>
                        <input type="text" id="player_name" name="player_name"
                               placeholder="例: 山田 太郎" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="player_number">背番号</label>
                        <input type="number" id="player_number" name="player_number"
                               placeholder="例: 10" min="0" max="99" value="0">
                    </div>
                    <div class="form-group">
                        <label for="player_team">チーム名（任意）</label>
                        <input type="text" id="player_team" name="player_team"
                               placeholder="例: FC Amister" maxlength="100">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">登録する</button>
                </form>
            </div>

            <!-- 選手一覧 -->
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

        <!-- === 行為タブ === -->
        <div class="tab-content" id="tab-action">
            <div class="card" id="action">
                <div class="card-title">行為を登録</div>
                <form method="post" action="register.php#action">
                    <input type="hidden" name="type" value="action">
                    <div class="form-group">
                        <label for="action_name">行為名 <span style="color:red">*</span></label>
                        <input type="text" id="action_name" name="action_name"
                               placeholder="例: パス成功" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="action_points">ポイント値（負値=減点）</label>
                        <input type="number" id="action_points" name="action_points"
                               placeholder="例: 1 または -1" value="1" min="-10" max="10">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">登録する</button>
                </form>
            </div>

            <!-- 行為一覧 -->
            <div class="card">
                <div class="card-title">登録済み行為 (<?= count($actions) ?>件)</div>
                <?php if (empty($actions)): ?>
                    <p class="text-muted" style="font-size:.85rem;">まだ登録されていません</p>
                <?php else: ?>
                    <table class="stats-table">
                        <thead>
                            <tr><th>行為名</th><th>ポイント</th><th>カテゴリ</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actions as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['name']) ?></td>
                                    <td><?= $a['point_value'] >= 0 ? '+' : '' ?><?= (int)$a['point_value'] ?></td>
                                    <td><?= $a['category'] === 'positive' ? '&#9989;' : '&#10060;' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- === 試合タブ === -->
        <div class="tab-content" id="tab-match">
            <div class="card" id="match">
                <div class="card-title">試合を登録</div>
                <form method="post" action="register.php#match">
                    <input type="hidden" name="type" value="match">
                    <div class="form-group">
                        <label for="match_date">試合日 <span style="color:red">*</span></label>
                        <input type="date" id="match_date" name="match_date"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="opponent">対戦相手 <span style="color:red">*</span></label>
                        <input type="text" id="opponent" name="opponent"
                               placeholder="例: FC ライバル" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="location">場所（任意）</label>
                        <input type="text" id="location" name="location"
                               placeholder="例: 中央グラウンド" maxlength="100">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">登録する</button>
                </form>
            </div>

            <!-- 試合一覧 -->
            <div class="card">
                <div class="card-title">登録済み試合 (<?= count($matches) ?>件)</div>
                <?php if (empty($matches)): ?>
                    <p class="text-muted" style="font-size:.85rem;">まだ登録されていません</p>
                <?php else: ?>
                    <table class="stats-table">
                        <thead>
                            <tr><th>日付</th><th>対戦相手</th><th>場所</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['match_date']) ?></td>
                                    <td><?= htmlspecialchars($m['opponent']) ?></td>
                                    <td><?= htmlspecialchars($m['location']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </main>

    <script>
    // タブ切り替え
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('tab-' + target).classList.add('active');
        });
    });

    // URLハッシュに応じてタブを自動切り替え
    (function () {
        const hash = location.hash.replace('#', '');
        if (['player', 'action', 'match'].includes(hash)) {
            document.querySelector(`[data-tab="${hash}"]`)?.click();
        }
    })();
    </script>
</body>
</html>
