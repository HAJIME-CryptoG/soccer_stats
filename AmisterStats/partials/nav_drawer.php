<?php
/**
 * ナビゲーションドロワー（全ページ共通）
 * 使い方: <?php $nav_current = 'record'; require __DIR__ . '/../partials/nav_drawer.php'; ?>
 * $nav_current: 'home' | 'record' | 'view' | 'register' | 'list'
 */
$nav_current = $nav_current ?? '';

$nav_items = [
    ['key' => 'home',     'href' => 'index.php',    'icon' => '🏠', 'label' => 'ホーム'],
    ['key' => 'record',   'href' => 'record.php',   'icon' => '📝', 'label' => '記録する'],
    ['key' => 'view',     'href' => 'view.php',     'icon' => '📊', 'label' => '集計を見る'],
    ['key' => 'register', 'href' => 'register.php', 'icon' => '⚙️', 'label' => 'マスター登録'],
    ['key' => 'list',     'href' => 'list.php',     'icon' => '📋', 'label' => '一覧を見る'],
];
?>
<!-- ナビゲーションオーバーレイ -->
<div class="nav-overlay" id="nav-overlay" onclick="closeNav()"></div>

<!-- ナビゲーションドロワー -->
<div class="nav-drawer" id="nav-drawer" role="dialog" aria-label="ナビゲーション">
    <div class="nav-drawer-header">
        <span>⚽ AmisterStats</span>
        <button class="nav-drawer-close" onclick="closeNav()" aria-label="閉じる">✕</button>
    </div>
    <nav>
        <?php foreach ($nav_items as $item): ?>
        <a class="nav-drawer-item <?= $nav_current === $item['key'] ? 'current' : '' ?>"
           href="<?= $item['href'] ?>">
            <span class="nav-drawer-icon"><?= $item['icon'] ?></span>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<script>
function openNav() {
    document.getElementById('nav-drawer').classList.add('open');
    document.getElementById('nav-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeNav() {
    document.getElementById('nav-drawer').classList.remove('open');
    document.getElementById('nav-overlay').classList.remove('open');
    document.body.style.overflow = '';
}
// ESCキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNav();
});
</script>
