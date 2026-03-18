<?php
/**
 * ナビゲーションドロワー（全ページ共通）
 * 使い方: <?php $nav_current = 'record'; require __DIR__ . '/../partials/nav_drawer.php'; ?>
 * $nav_current: 'home' | 'record' | 'view' | 'register' | 'list'
 */
?>
<style>
/* ナビゲーションドロワー — 外部CSSが未ロードの場合も確実に動作させる保険スタイル */
.nav-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;opacity:0;pointer-events:none;transition:opacity .25s;}
.nav-overlay.open{opacity:1;pointer-events:auto;}
.nav-drawer{position:fixed;top:0;right:0;width:240px;height:100%;background:#fff;z-index:201;transform:translateX(100%);transition:transform .25s cubic-bezier(.4,0,.2,1);box-shadow:-4px 0 20px rgba(0,0,0,.25);display:flex;flex-direction:column;overflow:hidden;}
.nav-drawer.open{transform:translateX(0);}
.nav-drawer-header{background:#1a7f3c;color:#fff;padding:.9rem 1rem;font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-shrink:0;}
.nav-drawer-close{background:none;border:none;color:#fff;font-size:1.2rem;cursor:pointer;padding:.2rem .4rem;border-radius:4px;line-height:1;}
.nav-drawer nav{flex:1;padding:.4rem 0;overflow-y:auto;}
.nav-drawer-item{display:flex;align-items:center;gap:.75rem;padding:.85rem 1.2rem;text-decoration:none;color:#212121;font-size:.95rem;font-weight:600;border-left:3px solid transparent;}
.nav-drawer-item.current{background:rgba(26,127,60,.08);color:#1a7f3c;border-left-color:#1a7f3c;}
.nav-drawer-icon{font-size:1.15rem;width:1.5rem;text-align:center;}
</style>
<?php
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
