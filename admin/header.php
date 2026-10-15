<?php
// ---- Yetki kapisi: HICBIR cikti basmadan once calisir ----
require_once __DIR__ . '/../includes/bootstrap.php';
app_session_start();
$con = db();

// Cikis: artik POST + CSRF (GET logout CSRF riskini kapatir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cikis'])) {
    csrf_dogrula();
    admin_cikis();
    redirect('admin/login.php');
}

if (!isset($_SESSION["loginkey"]) || $_SESSION["loginkey"] == "") {
    redirect('admin/login.php');
}

// Giris yapmis kullanici bilgileri
$admin_kadi = isset($_SESSION['loginkey']) ? $_SESSION['loginkey'] : '';

// Aktif menü tespiti (Host basligina guvenmeden, sadece istenen yol kullanilir)
$aktif_yol = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$aktif_yol = str_replace('\\', '/', $aktif_yol);

// Sayfaya ozel CSS kapsama sinifi: SCRIPT_NAME'den turetilir (ornek: pg-urun-duzelt).
// Boylece her admin sayfasinin stilleri admin-style.css icinde yalnizca kendi sayfasiyla
// eslesir; sayfalar arasi secici cakismasi (ve gorsel kayma) olmaz.
$page_script = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '';
$page_class = '';
if (preg_match('#/admin/(.+?)\.php$#', str_replace('\\', '/', $page_script), $mm)) {
    $page_class = 'pg-' . strtolower(str_replace('/', '-', $mm[1]));
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?= tema_head() ?>
    <title>ShopCore | Admin</title>
    <?= csrf_meta() ?>
    <!-- Bootstrap 5 CSS + SweetAlert2 CSS: yerel kopyalar (surum dosya adinda) -->
    <?= varlik_css(APP_VARLIK_BOOTSTRAP_CSS) ?>
    <?= varlik_css(APP_VARLIK_SWAL_CSS) ?>
    <!-- Ikonlar: harici kutuphane yok, yerel Lucide sprite'i (includes/ikonlar.php) -->
    <?= varlik_css('content/css/style.css') ?>
    <?= varlik_css('content/css/admin-style.css') ?>
</head>

<body class="admin <?= $page_class ?>">
<?php require __DIR__ . '/../includes/ikonlar.php'; ?>
<nav class="navbar navbar-expand-lg admin-topbar fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?= APP_BASE_URL ?>admin/index.php">
            <span class="brand-kutu"><svg class="ik" aria-hidden="true"><use href="#lk-shopping-bag"></use></svg></span> ShopCore
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Menüyü aç/kapat">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php
                $li_aktif = function ($needle) use ($aktif_yol) {
                    return (strpos($aktif_yol, $needle) !== false) ? 'active' : '';
                };
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $li_aktif('/admin/index.php') ?>" href="<?= APP_BASE_URL ?>admin/index.php">Anasayfa</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $li_aktif('/admin/urun/') ?>" href="<?= APP_BASE_URL ?>admin/urun/liste.php">Ürünler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $li_aktif('/admin/kategori/') ?>" href="<?= APP_BASE_URL ?>admin/kategori/liste.php">Kategoriler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $li_aktif('/admin/siparis/') ?>" href="<?= APP_BASE_URL ?>admin/siparis/liste.php">Siparişler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $li_aktif('/admin/kullanici/') ?>" href="<?= APP_BASE_URL ?>admin/kullanici/liste.php">Kullanıcılar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $li_aktif('/admin/yetkili/') ?>" href="<?= APP_BASE_URL ?>admin/yetkili/liste.php">Yetkililer</a>
                </li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item d-flex align-items-center me-2">
                    <button type="button" class="btn btn-topbar-outline tema-btn" aria-label="Tema: Açık" title="Tema: Açık">
                        <svg class="ik" aria-hidden="true"><use href="#lk-sun"></use></svg>
                    </button>
                </li>
                <li class="nav-item">
                    <form method="post" class="d-inline" action="<?= APP_BASE_URL ?>admin/index.php">
                        <?= csrf_alan() ?>
                        <input type="hidden" name="cikis" value="1">
                        <button type="submit" class="btn btn-topbar-outline">
                            <svg class="ik me-1" aria-hidden="true"><use href="#lk-log-out"></use></svg>Oturumu kapat
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php
// PRG sonrasi bildirimler tek noktadan basilir: her admin sayfasi kendi
// "islem tamam" mesajini kodlamaz (yonlendirme hedefi hangi sayfa olursa olsun).
$flash = flash_render();
if ($flash !== ''): ?>
<div class="container admin-flash">
    <?= $flash ?>
</div>
<?php endif; ?>
