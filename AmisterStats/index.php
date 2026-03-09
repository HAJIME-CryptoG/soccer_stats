<?php
// ホーム画面 — 記録 / 見る / 登録の分岐
$title = 'AmisterStats';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a7f3c">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-header">
        <h1>&#9917; AmisterStats</h1>
    </header>

    <main class="container">
        <p class="text-muted text-center mt-2" style="font-size:.9rem;">
            アマチュアサッカー スタッツ記録アプリ
        </p>

        <nav class="home-grid mt-3">
            <a class="home-btn" href="record.php">
                <span class="icon">&#128221;</span>
                記録する
            </a>
            <a class="home-btn" href="view.php" style="background:#1565c0;">
                <span class="icon">&#128202;</span>
                集計を見る
            </a>
            <a class="home-btn" href="register.php" style="background:#6a1b9a;">
                <span class="icon">&#9881;&#65039;</span>
                マスター登録
            </a>
        </nav>
    </main>
</body>
</html>
