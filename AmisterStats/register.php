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
                $flash[] = ['error', 'プレー名を入力してください'];
            } else {
                $pdo->prepare(
                    'INSERT INTO actions (name, point_value, category) VALUES (?, ?, ?)'
                )->execute([$name, $points, $cat]);
                $flash[] = ['success', "プレー「{$name}」({$points}pt) を登録しました"];
            }

        } elseif ($type === 'match') {
            $date            = $_POST['match_date']       ?? '';
            $opponent        = trim($_POST['opponent']    ?? '');
            $location        = trim($_POST['location']    ?? '');
            $match_type      = ($_POST['match_type'] ?? '') === 'official' ? 'official' : 'friendly';
            $tournament_name = $match_type === 'official' ? trim($_POST['tournament_name'] ?? '') : '';

            if (!$date || !$opponent) {
                $flash[] = ['error', '日付と対戦相手を入力してください'];
            } else {
                // match_type / tournament_name カラムが無ければ自動追加（マイグレーション）
                $cols = $pdo->query("SHOW COLUMNS FROM matches LIKE 'match_type'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE matches ADD COLUMN match_type ENUM('friendly','official') NOT NULL DEFAULT 'friendly'");
                    $pdo->exec("ALTER TABLE matches ADD COLUMN tournament_name VARCHAR(100) NOT NULL DEFAULT ''");
                }
                $pdo->prepare(
                    'INSERT INTO matches (match_date, opponent, location, match_type, tournament_name) VALUES (?, ?, ?, ?, ?)'
                )->execute([$date, $opponent, $location, $match_type, $tournament_name]);
                $label = $match_type === 'official'
                    ? "【公式】{$tournament_name} {$date} vs {$opponent}"
                    : "【練習】{$date} vs {$opponent}";
                $flash[] = ['success', "{$label} を登録しました"];
            }

        } elseif ($type === 'match_update') {
            $id              = (int)($_POST['match_id']        ?? 0);
            $date            = $_POST['match_date']            ?? '';
            $opponent        = trim($_POST['opponent']         ?? '');
            $location        = trim($_POST['location']         ?? '');
            $match_type      = ($_POST['match_type'] ?? '') === 'official' ? 'official' : 'friendly';
            $tournament_name = $match_type === 'official' ? trim($_POST['tournament_name'] ?? '') : '';

            if (!$id || !$date || !$opponent) {
                $flash[] = ['error', '日付と対戦相手を入力してください'];
            } else {
                $pdo->prepare(
                    'UPDATE matches SET match_date=?, opponent=?, location=?, match_type=?, tournament_name=? WHERE id=?'
                )->execute([$date, $opponent, $location, $match_type, $tournament_name, $id]);
                $flash[] = ['success', "試合を更新しました"];
            }

        } elseif ($type === 'match_delete') {
            $id = (int)($_POST['match_id'] ?? 0);
            if ($id) {
                $pdo->prepare('DELETE FROM matches WHERE id=?')->execute([$id]);
                $flash[] = ['success', '試合を削除しました'];
            }

        } elseif ($type === 'player_update') {
            $id     = (int)($_POST['player_id']     ?? 0);
            $name   = trim($_POST['player_name']    ?? '');
            $number = (int)($_POST['player_number'] ?? 0);
            $team   = trim($_POST['player_team']    ?? '');
            if (!$id || $name === '') {
                $flash[] = ['error', '選手名を入力してください'];
            } else {
                $pdo->prepare('UPDATE players SET name=?, number=?, team=? WHERE id=?')
                    ->execute([$name, $number, $team, $id]);
                $flash[] = ['success', "選手「{$name}」を更新しました"];
            }

        } elseif ($type === 'player_delete') {
            $id = (int)($_POST['player_id'] ?? 0);
            if ($id) {
                $pdo->prepare('DELETE FROM players WHERE id=?')->execute([$id]);
                $flash[] = ['success', '選手を削除しました'];
            }

        } elseif ($type === 'action_update') {
            $id     = (int)($_POST['action_id']     ?? 0);
            $name   = trim($_POST['action_name']    ?? '');
            $points = (int)($_POST['action_points'] ?? 0);
            $cat    = $points >= 0 ? 'positive' : 'negative';
            if (!$id || $name === '') {
                $flash[] = ['error', 'プレー名を入力してください'];
            } else {
                $pdo->prepare('UPDATE actions SET name=?, point_value=?, category=? WHERE id=?')
                    ->execute([$name, $points, $cat, $id]);
                $flash[] = ['success', "プレー「{$name}」を更新しました"];
            }

        } elseif ($type === 'action_delete') {
            $id = (int)($_POST['action_id'] ?? 0);
            if ($id) {
                $pdo->prepare('DELETE FROM actions WHERE id=?')->execute([$id]);
                $flash[] = ['success', 'プレーを削除しました'];
            }

        } elseif ($type === 'reset_logs') {
            $confirm = trim($_POST['reset_confirm'] ?? '');
            if ($confirm !== 'リセット') {
                $flash[] = ['error', '確認ワードが一致しません'];
            } else {
                $count = (int)$pdo->query('SELECT COUNT(*) FROM play_logs')->fetchColumn();
                $pdo->exec('DELETE FROM play_logs');
                $flash[] = ['success', "プレーデータを全件削除しました（{$count}件）。選手・試合・プレー設定はそのまま残っています。"];
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
        <button class="hamburger-btn" onclick="openNav()" aria-label="メニューを開く">
            <span></span><span></span><span></span>
        </button>
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
            <button class="tab-btn" data-tab="action" type="button">プレー</button>
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
                            <tr><th>#</th><th>氏名</th><th>チーム</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $p): ?>
                                <tr>
                                    <td><?= (int)$p['number'] ?></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['team']) ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button"
                                            style="font-size:.75rem;padding:.2rem .5rem;background:#1565c0;color:#fff;border:none;border-radius:4px;cursor:pointer;"
                                            onclick='openPlayerModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>
                                            編集
                                        </button>
                                        <form method="post" action="register.php#player" style="display:inline;"
                                              onsubmit="return confirm('この選手を削除しますか？')">
                                            <input type="hidden" name="type" value="player_delete">
                                            <input type="hidden" name="player_id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit"
                                                style="font-size:.75rem;padding:.2rem .5rem;background:#e53935;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                                                削除
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- 選手編集モーダル -->
                    <div id="player-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;">
                        <div style="background:#fff;border-radius:12px;padding:1.2rem;width:min(92vw,400px);">
                            <div style="font-weight:700;font-size:1rem;margin-bottom:1rem;">選手を編集</div>
                            <form method="post" action="register.php#player">
                                <input type="hidden" name="type" value="player_update">
                                <input type="hidden" name="player_id" id="edit-player-id">
                                <div class="form-group">
                                    <label>選手名 <span style="color:red">*</span></label>
                                    <input type="text" name="player_name" id="edit-player-name" maxlength="100" required>
                                </div>
                                <div class="form-group">
                                    <label>背番号</label>
                                    <input type="number" name="player_number" id="edit-player-number" min="0" max="99">
                                </div>
                                <div class="form-group">
                                    <label>チーム名</label>
                                    <input type="text" name="player_team" id="edit-player-team" maxlength="100">
                                </div>
                                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                    <button type="submit" class="btn btn-primary" style="flex:1;">更新する</button>
                                    <button type="button" onclick="closePlayerModal()"
                                        style="flex:1;padding:.7rem;border:1px solid #ccc;border-radius:8px;background:#fff;cursor:pointer;">
                                        キャンセル
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- === プレータブ === -->
        <div class="tab-content" id="tab-action">
            <div class="card" id="action">
                <div class="card-title">プレーを登録</div>
                <form method="post" action="register.php#action">
                    <input type="hidden" name="type" value="action">
                    <div class="form-group">
                        <label for="action_name">プレー名 <span style="color:red">*</span></label>
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

            <!-- プレー一覧 -->
            <div class="card">
                <div class="card-title">登録済みプレー (<?= count($actions) ?>件)</div>
                <?php if (empty($actions)): ?>
                    <p class="text-muted" style="font-size:.85rem;">まだ登録されていません</p>
                <?php else: ?>
                    <table class="stats-table">
                        <thead>
                            <tr><th>プレー名</th><th>ポイント</th><th>カテゴリ</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actions as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['name']) ?></td>
                                    <td><?= $a['point_value'] >= 0 ? '+' : '' ?><?= (int)$a['point_value'] ?></td>
                                    <td><?= $a['category'] === 'positive' ? '&#9989;' : '&#10060;' ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button"
                                            style="font-size:.75rem;padding:.2rem .5rem;background:#1565c0;color:#fff;border:none;border-radius:4px;cursor:pointer;"
                                            onclick='openActionModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'>
                                            編集
                                        </button>
                                        <form method="post" action="register.php#action" style="display:inline;"
                                              onsubmit="return confirm('このプレーを削除しますか？')">
                                            <input type="hidden" name="type" value="action_delete">
                                            <input type="hidden" name="action_id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit"
                                                style="font-size:.75rem;padding:.2rem .5rem;background:#e53935;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                                                削除
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- プレー編集モーダル -->
                    <div id="action-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;">
                        <div style="background:#fff;border-radius:12px;padding:1.2rem;width:min(92vw,400px);">
                            <div style="font-weight:700;font-size:1rem;margin-bottom:1rem;">プレーを編集</div>
                            <form method="post" action="register.php#action">
                                <input type="hidden" name="type" value="action_update">
                                <input type="hidden" name="action_id" id="edit-action-id">
                                <div class="form-group">
                                    <label>プレー名 <span style="color:red">*</span></label>
                                    <input type="text" name="action_name" id="edit-action-name" maxlength="100" required>
                                </div>
                                <div class="form-group">
                                    <label>ポイント値（負値=減点）</label>
                                    <input type="number" name="action_points" id="edit-action-points" min="-10" max="10">
                                </div>
                                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                    <button type="submit" class="btn btn-primary" style="flex:1;">更新する</button>
                                    <button type="button" onclick="closeActionModal()"
                                        style="flex:1;padding:.7rem;border:1px solid #ccc;border-radius:8px;background:#fff;cursor:pointer;">
                                        キャンセル
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                        <label>試合種別 <span style="color:red">*</span></label>
                        <div style="display:flex;gap:1.5rem;margin-top:.3rem;">
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                                <input type="radio" name="match_type" value="friendly" checked
                                       onchange="toggleTournament(this.value)">
                                練習試合
                            </label>
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                                <input type="radio" name="match_type" value="official"
                                       onchange="toggleTournament(this.value)">
                                公式試合（大会）
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="tournament-group" style="display:none;">
                        <label for="tournament_name">大会名</label>
                        <input type="text" id="tournament_name" name="tournament_name"
                               placeholder="例: 県リーグ第3節" maxlength="100">
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
                            <tr><th>日付</th><th>種別</th><th>大会名</th><th>対戦相手</th><th>場所</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['match_date']) ?></td>
                                    <td><?= ($m['match_type'] ?? 'friendly') === 'official' ? '🏆公式' : '🤝練習' ?></td>
                                    <td><?= htmlspecialchars($m['tournament_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($m['opponent']) ?></td>
                                    <td><?= htmlspecialchars($m['location']) ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button"
                                            class="btn btn-sm"
                                            style="font-size:.75rem;padding:.2rem .5rem;background:#1565c0;color:#fff;border:none;border-radius:4px;cursor:pointer;"
                                            onclick='openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)'>
                                            編集
                                        </button>
                                        <form method="post" action="register.php#match" style="display:inline;"
                                              onsubmit="return confirm('この試合を削除しますか？')">
                                            <input type="hidden" name="type" value="match_delete">
                                            <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>">
                                            <button type="submit"
                                                style="font-size:.75rem;padding:.2rem .5rem;background:#e53935;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                                                削除
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- 編集モーダル -->
                    <div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;">
                        <div style="background:#fff;border-radius:12px;padding:1.2rem;width:min(92vw,440px);max-height:90vh;overflow-y:auto;">
                            <div style="font-weight:700;font-size:1rem;margin-bottom:1rem;">試合を編集</div>
                            <form method="post" action="register.php#match">
                                <input type="hidden" name="type" value="match_update">
                                <input type="hidden" name="match_id" id="edit-id">
                                <div class="form-group">
                                    <label>試合日 <span style="color:red">*</span></label>
                                    <input type="date" name="match_date" id="edit-date" required>
                                </div>
                                <div class="form-group">
                                    <label>対戦相手 <span style="color:red">*</span></label>
                                    <input type="text" name="opponent" id="edit-opponent" maxlength="100" required>
                                </div>
                                <div class="form-group">
                                    <label>試合種別</label>
                                    <div style="display:flex;gap:1.5rem;margin-top:.3rem;">
                                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                                            <input type="radio" name="match_type" id="edit-type-friendly" value="friendly"
                                                   onchange="toggleEditTournament(this.value)"> 練習試合
                                        </label>
                                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                                            <input type="radio" name="match_type" id="edit-type-official" value="official"
                                                   onchange="toggleEditTournament(this.value)"> 公式試合（大会）
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group" id="edit-tournament-group">
                                    <label>大会名</label>
                                    <input type="text" name="tournament_name" id="edit-tournament" maxlength="100">
                                </div>
                                <div class="form-group">
                                    <label>場所（任意）</label>
                                    <input type="text" name="location" id="edit-location" maxlength="100">
                                </div>
                                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                    <button type="submit" class="btn btn-primary" style="flex:1;">更新する</button>
                                    <button type="button" onclick="closeEditModal()"
                                        style="flex:1;padding:.7rem;border:1px solid #ccc;border-radius:8px;background:#fff;cursor:pointer;">
                                        キャンセル
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================
             危険ゾーン：プレーデータ全リセット
             ================================================================ -->
        <div class="card" style="border:2px solid #e53935;margin-top:1.5rem;">
            <div class="card-title" style="color:#e53935;">⚠️ プレーデータのリセット</div>
            <p style="font-size:.85rem;color:#555;margin:0 0 .8rem;">
                <strong>play_logs テーブルを全件削除します。</strong><br>
                選手・試合・プレー設定のデータは削除されません。<br>
                テスト入力を一括削除してから本番配布する用途向けです。
            </p>
            <?php
                try {
                    $log_count = (int)get_pdo()->query('SELECT COUNT(*) FROM play_logs')->fetchColumn();
                } catch (Exception $e) { $log_count = '?'; }
            ?>
            <p style="font-size:.9rem;font-weight:700;margin:0 0 .8rem;">
                現在の記録数：<span style="color:#e53935;"><?= $log_count ?>件</span>
            </p>
            <form method="post" action="register.php" id="resetForm"
                  onsubmit="return confirmReset()">
                <input type="hidden" name="type" value="reset_logs">
                <div class="form-group">
                    <label for="reset_confirm">
                        確認のため <strong>「リセット」</strong> と入力してください
                    </label>
                    <input type="text" id="reset_confirm" name="reset_confirm"
                           placeholder="リセット" autocomplete="off"
                           style="border-color:#e53935;">
                </div>
                <button type="submit" class="btn btn-block"
                        style="background:#e53935;color:#fff;font-weight:700;">
                    🗑️ プレーデータを全件削除する
                </button>
            </form>
        </div>

        <?php endif; ?>
    </main>

    <script>
    // 選手編集モーダル
    function openPlayerModal(p) {
        document.getElementById('edit-player-id').value     = p.id;
        document.getElementById('edit-player-name').value   = p.name;
        document.getElementById('edit-player-number').value = p.number;
        document.getElementById('edit-player-team').value   = p.team || '';
        document.getElementById('player-modal').style.display = 'flex';
    }
    function closePlayerModal() {
        document.getElementById('player-modal').style.display = 'none';
    }
    const _playerModal = document.getElementById('player-modal');
    if (_playerModal) {
        _playerModal.addEventListener('click', function(e) {
            if (e.target === this) closePlayerModal();
        });
    }

    // 行為編集モーダル
    function openActionModal(a) {
        document.getElementById('edit-action-id').value     = a.id;
        document.getElementById('edit-action-name').value   = a.name;
        document.getElementById('edit-action-points').value = a.point_value;
        document.getElementById('action-modal').style.display = 'flex';
    }
    function closeActionModal() {
        document.getElementById('action-modal').style.display = 'none';
    }
    const _actionModal = document.getElementById('action-modal');
    if (_actionModal) {
        _actionModal.addEventListener('click', function(e) {
            if (e.target === this) closeActionModal();
        });
    }

    // 試合編集モーダル
    function openEditModal(m) {
        document.getElementById('edit-id').value         = m.id;
        document.getElementById('edit-date').value       = m.match_date;
        document.getElementById('edit-opponent').value   = m.opponent;
        document.getElementById('edit-location').value   = m.location || '';
        document.getElementById('edit-tournament').value = m.tournament_name || '';
        const isOfficial = m.match_type === 'official';
        document.getElementById('edit-type-friendly').checked = !isOfficial;
        document.getElementById('edit-type-official').checked = isOfficial;
        document.getElementById('edit-tournament-group').style.display = isOfficial ? '' : 'none';
        const modal = document.getElementById('edit-modal');
        modal.style.display = 'flex';
    }
    function closeEditModal() {
        document.getElementById('edit-modal').style.display = 'none';
    }
    function toggleEditTournament(val) {
        document.getElementById('edit-tournament-group').style.display =
            val === 'official' ? '' : 'none';
    }
    // モーダル外クリックで閉じる（試合がゼロの場合はモーダルが存在しないのでnullチェック）
    const _editModal = document.getElementById('edit-modal');
    if (_editModal) {
        _editModal.addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    }

    // 大会名フィールドの表示切り替え
    function toggleTournament(val) {
        const grp = document.getElementById('tournament-group');
        const inp = document.getElementById('tournament_name');
        if (val === 'official') {
            grp.style.display = '';
            inp.required = true;
        } else {
            grp.style.display = 'none';
            inp.required = false;
            inp.value = '';
        }
    }

    // タブ切り替え（イベント委譲）
    function switchTab(name) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const btn = document.querySelector('[data-tab="' + name + '"]');
        const content = document.getElementById('tab-' + name);
        if (btn) btn.classList.add('active');
        if (content) content.classList.add('active');
    }
    const _tabBar = document.querySelector('.tab-bar');
    if (_tabBar) {
        _tabBar.addEventListener('click', function(e) {
            const btn = e.target.closest('.tab-btn');
            if (btn && btn.dataset.tab) switchTab(btn.dataset.tab);
        });
    }

    // プレーデータリセット確認
    function confirmReset() {
        const val = document.getElementById('reset_confirm').value;
        if (val !== 'リセット') {
            alert('「リセット」と正確に入力してください');
            return false;
        }
        return confirm('本当にプレーデータを全件削除しますか？\nこの操作は取り消せません。');
    }

    // URLハッシュに応じてタブを自動切り替え
    (function () {
        const hash = location.hash.replace('#', '');
        if (['player', 'action', 'match'].includes(hash)) {
            switchTab(hash);
        }
    })();
    </script>
    <?php $nav_current = 'register'; require __DIR__ . '/partials/nav_drawer.php'; ?>
</body>
</html>
