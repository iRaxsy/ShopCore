<?php
require_once __DIR__ . '/includes/bootstrap.php';

app_session_start();
$con = db();

// Sayfaya gore <title> ve aciklama (basename eslesmesi yoksa varsayilan marka adi)
$baslik_haritasi = [
    'index.php'       => ['ShopCore | Alışveriş', 'ShopCore ürünleri, kampanyalar ve ücretsiz kargo fırsatları'],
    'urunler.php'     => ['Ürünler | ShopCore', 'Tüm ürünleri fiyat ve kategoriye göre filtreleyin'],
    'urundetay.php'   => ['Ürün Detayı | ShopCore', 'Ürün özellikleri, fiyatı ve galerisi'],
    'sepetim.php'     => ['Sepetim | ShopCore', 'Sepetinizdeki ürünler ve toplam tutar'],
    'satinal.php'     => ['Ödeme | ShopCore', 'Adres ve ödeme adımları'],
    'siparis_onay.php'=> ['Sipariş Onayı | ShopCore', 'Sipariş özeti'],
    'hakkimizda.php'  => ['Hakkımızda | ShopCore', 'ShopCore hakkında ve iletişim formu'],
    'giris.php'       => ['Giriş | ShopCore', 'Hesabınıza giriş yapın'],
    'kayit.php'       => ['Kayıt Ol | ShopCore', 'Alışveriş hesabınızı oluşturun'],
    'hesabim.php'     => ['Hesabım | ShopCore', 'Hesap ve teslimat bilgileriniz'],
    'siparislerim.php'=> ['Siparişlerim | ShopCore', 'Geçmiş siparişleriniz'],
];
$bu_sayfa        = basename($_SERVER['PHP_SELF']);
$sayfa_basligi   = isset($baslik_haritasi[$bu_sayfa]) ? $baslik_haritasi[$bu_sayfa][0] : 'ShopCore';
$sayfa_aciklamasi = isset($baslik_haritasi[$bu_sayfa]) ? $baslik_haritasi[$bu_sayfa][1] : '';

// urundetay.php, header.php'yi include etmeden once $urunadi'yi set eder
if ($bu_sayfa === 'urundetay.php' && !empty($urunadi)) {
    $sayfa_basligi = $urunadi . ' | ShopCore';
}
?>

<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?= tema_head() ?>
    <title><?= esc($sayfa_basligi) ?></title>
    <meta name="description" content="<?= esc($sayfa_aciklamasi) ?>">
    <?= csrf_meta() ?>

    <!-- Yazi tipi: Quicksand artik yerel degisken dosya (harici origin yok,
         Google Fonts link'i/preconnect'i gerekmiyor). Preload BILEREK yok:
         quicksand.css iki alt kume (latin + latin-ext) iceriyor ve unicode-range
         yuzunden hangisinin gerekecegi sayfadaki metne bagli; bosuna preload
         edilen kume "preloaded but not used" uyarisi uretiyor. -->
    <?= varlik_css(APP_VARLIK_FONT_CSS) ?>

    <!-- Bootstrap 5 CSS + SweetAlert2 CSS: yerel kopyalar (surum dosya adinda) -->
    <?= varlik_css(APP_VARLIK_BOOTSTRAP_CSS) ?>
    <?= varlik_css(APP_VARLIK_SWAL_CSS) ?>
    
    <!-- Ikonlar: harici kutuphane yok, yerel Lucide sprite'i (includes/ikonlar.php) -->
    <!-- Bizim stil dosyamız (parmak izi: dosya degisince adres de degisir) -->
    <?= varlik_css('content/css/style.css') ?>
</head>
<body class="site d-flex flex-column min-vh-100">
    <?php require __DIR__ . '/includes/ikonlar.php'; ?>
    <!-- Üst kampanya ve sepet bilgisi -->
    <div class="container-fluid bg-success">
        <div class="container">
            <div class="row p-2 align-items-center kampanya-satiri">
                <div class="col-md-8">
                    <span class="text-white kampanya-metni">Kampanya: <?= kargo_esigi() ?>&#8378; üzeri alışverişlerinizde ücretsiz kargo</span>
                </div>
                <div class="col-md-4 text-md-end text-white">
                    <a class="link1 text-white text-decoration-none sepet-dugme" href="sepetim.php">
                        <span class="sepet-kutu" aria-hidden="true">
                            <svg class="ik" aria-hidden="true"><use href="#lk-shopping-bag"></use></svg>
                        </span>
                        <span class="sepet-etiket">Sepetiniz</span>
                        <span class="sepet-sayac" id="urun-sayisi"><?php
                            // Rozet, sepet tablosu ve odeme sayfasiyla ayni kaynaktan okunur
                            echo (int)sepet_ozeti()['urun_sayisi'];
                        ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Ana navigasyon -->
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light bg-body py-3">
            <a class="navbar-brand site-logo" href="<?= APP_BASE_URL ?>index.php" aria-label="ShopCore ana sayfa">
                <!-- Site logosu: gorsel dosyasi yok, CSS ikon + tipografi (style.css/.site-logo) -->
                <span class="logo-kutu" aria-hidden="true"><svg class="ik" aria-hidden="true"><use href="#lk-shopping-bag"></use></svg></span>
                <span class="logo-yazi">Shop<strong>Core</strong></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Urun arama formu: yalniz genis ekranda menunun içinde -->
                <form class="d-none d-lg-flex my-2 my-lg-0 me-3" action="urunler.php" method="get" name="form_ara">
                    <input class="form-control me-2" type="search" placeholder="Ürün ara..." aria-label="Ara" name="aranan">
                    <button class="btn btn-outline-success" type="submit">Ara</button>
                </form>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $bu_sayfa == 'index.php' ? 'active fw-bold' : ''; ?>" href="index.php">Anasayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $bu_sayfa == 'urunler.php' ? 'active fw-bold' : ''; ?>" href="urunler.php">Ürünler</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Kategoriler
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <?php
                            /* Ust kategori menuasi her sayfada DB'den okunur.
                             * Sorgu basarisizsa null doner ve "Kategoriler yuklenemedi"
                             * dalisi devreye girer.
                             */
                            try {
                                $kategoriler = db()->query('SELECT id, kategoriadi FROM kategoriler ORDER BY kategoriadi')->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                error_log('header kategori: ' . $e->getMessage());
                                $kategoriler = null;
                            }

                            if ($kategoriler === null) {
                                echo "<li><a class='dropdown-item text-danger' href='#'>Kategoriler yüklenemedi</a></li>";
                            } elseif ($kategoriler === []) {
                                // Bos liste: calismayan # link yerine Bootstrap'in menu ici
                                // metin etiketi (.dropdown-item-text) kullanılır. text-nowrap:
                                // .dropdown-item-text'te white-space: nowrap YOK, mesaj butondan
                                // genis oldugu icin satir kiriliyor; tek satirda kalsin.
                                echo '<li><p class="dropdown-item-text text-muted text-nowrap mb-0">Aktif bir kategori yok</p></li>';
                            } else {
                                foreach ($kategoriler as $kategori) {
                                    $url = url('urunler.php', [
                                        'id' => (int)$kategori['id'],
                                        'aranan' => '',
                                        'siralama' => 'akilli',
                                        'fiyat' => '0',
                                    ]);
                                    
                                    echo "<li><a class='dropdown-item' href='" . $url . "'>" .
                                         esc($kategori["kategoriadi"]) . "</a></li>";
                                }
                            }
                            ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $bu_sayfa == 'hakkimizda.php' ? 'active fw-bold' : ''; ?>" href="hakkimizda.php">Hakkımızda</a>
                    </li>
                    <?php if (kullanici_girisi_var_mi()): ?>
                        <?php $hesap = kullanici_bilgisi(); ?>
                        <!-- Kullanici hesap menuyu: cikis bilincli olarak POST + CSRF
                            (GET ile cikis, zorla cikis saldirisina acik oldugu icin
                            admin panelinde de kaldirilmisti). -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="hesapDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                                <svg class="ik me-1" aria-hidden="true"><use href="#lk-circle-user"></use></svg>
                                <span class="hesap-ad"><?= esc(ozetle($hesap['adsoyad'] ?? 'Hesabım', 18)) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end hesap-menu" aria-labelledby="hesapDropdown">
                                <li class="dropdown-header text-truncate"><?= esc($hesap['eposta'] ?? '') ?></li>
                                <li><a class="dropdown-item <?= $bu_sayfa === 'hesabim.php' ? 'active fw-bold' : '' ?>" href="hesabim.php">
                                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-user"></use></svg>Hesabım</a></li>
                                <li><a class="dropdown-item <?= $bu_sayfa === 'siparislerim.php' ? 'active fw-bold' : '' ?>" href="siparislerim.php">
                                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-receipt"></use></svg>Siparişlerim</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="cikis.php" class="px-2 py-1 mb-0">
                                        <?= csrf_alan() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <svg class="ik me-1" aria-hidden="true"><use href="#lk-log-out"></use></svg>Oturumu kapat
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($bu_sayfa, ['giris.php', 'kayit.php'], true) ? 'active fw-bold' : '' ?>"
                               href="<?= url('giris.php', ['geri' => $bu_sayfa]) ?>">
                                <svg class="ik me-1" aria-hidden="true"><use href="#lk-log-in"></use></svg>Giriş Yap
                            </a>
                        </li>
                        <li class="nav-item d-flex align-items-center">
                            <a class="btn btn-sm btn-outline-success" href="<?= url('kayit.php', ['geri' => $bu_sayfa]) ?>">
                                <svg class="ik me-1" aria-hidden="true"><use href="#lk-circle-user"></use></svg>Kayıt Ol
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center ms-lg-2">
                        <button type="button" class="tema-btn" aria-label="Tema: Açık" title="Tema: Açık">
                            <svg class="ik" aria-hidden="true"><use href="#lk-sun"></use></svg>
                        </button>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Dar ekran arama satiri: hamburger menusunu acmadan arama ve tema erisimi -->
        <div class="d-flex align-items-center gap-2 pb-3 d-lg-none header-ara-mobil">
            <form class="d-flex flex-grow-1" action="urunler.php" method="get" name="form_ara_mobil">
                <input class="form-control me-2" type="search" placeholder="Ürün ara..." aria-label="Ara" name="aranan">
                <button class="btn btn-outline-success" type="submit">Ara</button>
            </form>
            <button type="button" class="tema-btn flex-shrink-0" aria-label="Tema: Açık" title="Tema: Açık">
                <svg class="ik" aria-hidden="true"><use href="#lk-sun"></use></svg>
            </button>
        </div>
    </div>

    <!-- Sayfa icerigi footer.php'de kapanir; flex-grow-1 kisa sayfalarda footer'i alta iter -->
    <main class="flex-grow-1">
    <?php $flash = flash_render(); if ($flash !== ''): ?>
        <div class="container mt-4">
            <div class="row justify-content-center"><div class="col-md-10 col-lg-8"><?= $flash ?></div></div>
        </div>
    <?php endif; ?>
    