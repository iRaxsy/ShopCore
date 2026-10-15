</main>

<footer class="container-fluid bg-dark mt-5">
    <div class="container pt-4 pb-4">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h4 class="text-white mb-3">Hızlı Erişim</h4>
                <?php
                // Calismayan '#' linkler kaldirildi; yalnizca mevcut sayfalar listelenir
                $su_an = basename($_SERVER['PHP_SELF']);
                $footer_baglantilar = [
                    ['sayfa' => 'index.php',      'yol' => 'index.php',     'ikon' => 'home',          'ad' => 'Anasayfa'],
                    ['sayfa' => 'urunler.php',    'yol' => 'urunler.php',   'ikon' => 'boxes',         'ad' => 'Ürünler'],
                    ['sayfa' => 'sepetim.php',    'yol' => 'sepetim.php',   'ikon' => 'shopping-cart', 'ad' => 'Sepetim'],
                    ['sayfa' => 'hakkimizda.php', 'yol' => 'hakkimizda.php','ikon' => 'info',          'ad' => 'Hakkımızda'],
                    ['sayfa' => 'hakkimizda.php', 'yol' => 'hakkimizda.php#iletisimForm', 'ikon' => 'mail', 'ad' => 'İletişim'],
                ];
                // Hesap kisayolu oturum durumuna gore degisir: misafir giris/kayit
                // ekranina, kullanici kendi hesabina gider (calismayan '#' link yok).
                $footer_baglantilar[] = kullanici_girisi_var_mi()
                    ? ['sayfa' => 'hesabim.php', 'yol' => 'hesabim.php', 'ikon' => 'circle-user', 'ad' => 'Hesabım']
                    : ['sayfa' => 'giris.php',   'yol' => 'giris.php',   'ikon' => 'log-in',      'ad' => 'Giriş / Kayıt'];
                echo '<ul class="list-unstyled">';
                foreach ($footer_baglantilar as $baglanti) {
                    $aktif = ($su_an === $baglanti['sayfa']) ? ' fw-bold' : '';
                    echo '<li class="mb-2"><a href="' . url($baglanti['yol']) . '" class="text-light text-decoration-none' . $aktif . '">'
                       . '<svg class="ik me-2" aria-hidden="true"><use href="#lk-' . $baglanti['ikon'] . '"></use></svg>' . $baglanti['ad'] . '</a></li>';
                }
                ?>
                </ul>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h4 class="text-white mb-3">Fırsat Ürünleri</h4>
                <?php
                $con = isset($con) ? $con : db();
                /* Footer firsat urunleri her sayfada DB'den okunur; sorgu
                 * basarisizsa bos liste ile section sessizce bos kalir.
                 */
                try {
                    $veri = db()->query('SELECT id, urunadi, aciklama, fiyat, resim FROM urunler ORDER BY fiyat LIMIT 0,3')->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (PDOException $e) {
                    error_log('footer firsat urunleri: ' . $e->getMessage());
                    $veri = [];
                }
                foreach ($veri as $kayit) { 
                ?>
                <div class="row mb-3 align-items-center">
                    <div class="col-3">
                        <img src="<?= esc(resim_yolu($kayit['resim'], RESIM_KART_GENISLIK)) ?>" <?= resim_boyut($kayit['resim'], RESIM_KART_GENISLIK) ?> loading="lazy" decoding="async" class="img-fluid img-thumbnail" alt="<?= esc($kayit['urunadi']) ?>">
                    </div>
                    <div class="col-9">
                        <a href="<?= url('urundetay.php', ['id' => (int)$kayit['id']]) ?>" class="text-light text-decoration-none">
                            <p class="mb-1 fw-bold"><?= esc($kayit['urunadi']) ?></p>
                        </a>
                        <span class="badge bg-success p-2"><?= esc(para($kayit['fiyat'])) ?>&#8378;</span>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="col-md-4">
                <h4 class="text-white mb-3">İletişim</h4>
                <address class="text-light">
                    <?php
                    // Bilgiler config/yerel.php sabitlerinden okunur; bos alan gosterilmez
                    $iletisim_satirlari = [
                        ['etiket' => 'Adres',   'deger' => site_bilgisi('adres')],
                        ['etiket' => 'Telefon', 'deger' => site_bilgisi('telefon')],
                        ['etiket' => 'Faks',    'deger' => site_bilgisi('fax')],
                        ['etiket' => 'Web',     'deger' => site_bilgisi('web')],
                    ];
                    $iletisim_var = false;
                    foreach ($iletisim_satirlari as $satir) {
                        if ($satir['deger'] === '') {
                            continue;
                        }
                        $iletisim_var = true;
                        echo '<p class="mb-2"><strong>' . $satir['etiket'] . ':</strong> ' . esc($satir['deger']) . '</p>';
                    }
                    if (site_bilgisi('eposta') !== '') {
                        $iletisim_var = true;
                        echo '<p class="mb-0"><strong>Eposta:</strong> <a href="mailto:' . esc(site_bilgisi('eposta')) . '" class="text-light">' . esc(site_bilgisi('eposta')) . '</a></p>';
                    }
                    if (!$iletisim_var) {
                        echo '<p class="mb-0 text-light opacity-75">İletişim bilgileri henüz tanımlanmadı.</p>';
                    }
                    ?>
                </address>
            </div>
        </div>
        <div class="row mt-4 pt-3 border-top border-secondary">
            <div class="col-md-12 text-center">
                <p class="text-light mb-0">&copy; <?= date('Y') ?> ShopCore</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS (yerel kopya - CDN ve jQuery bagimliligi kaldirildi) -->
<?= varlik_js(APP_VARLIK_BOOTSTRAP_JS) ?>

<!-- Acik/koyu tema toggle motoru -->
<?= varlik_js('content/js/tema.js') ?>

<!-- SweetAlert2 (CSS'i header.php'den geldi) -->
<?= varlik_js(APP_VARLIK_SWAL_JS) ?>

<!-- Sepet AJAX yapilandirmasi (layout'tan enjekte edilir) -->
<script>
    window.SEPET_CONFIG = {
        yol: <?= json_encode(APP_BASE_URL) ?>,
        csrf: <?= json_encode(csrf_token()) ?>
    };
</script>
<?= varlik_js('content/js/sepet.js') ?>
</body>
</html>
