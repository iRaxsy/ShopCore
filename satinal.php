<?php
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();

// Yonlendirme/mantik, header HTML basmadan ONCE calismali. Odeme ekrani normalde
// sepetim.php'den POST ile acilir; sunucu tarafinda reddedilen formun yazilan
// degerlerini tasiyan tek kullanimlik snapshot (siparis_onay.php) de bu sayfayi
// acabilir, boylece kullanici odeme ekranindan hic ayrilmaz.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_SESSION['odeme_formu'])) {
    redirect('sepetim.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula(); // sepetim.php formundan gelen CSRF tokeni
}

// Giris yapmayan kullanici siparis veremez. Kapinin yeri bilincli: sepet ve
// CSRF tokeni korunur, kullanici giris sonrasi sepetine geri doner.
kullanici_zorunlu();

if (empty($_SESSION['sepet']) || count($_SESSION['sepet']) == 0) {
    redirect('sepetim.php');
}

$sepetOzeti = sepet_ozeti();

// Teslimat formu kullanici profilinden onceden doldurulur (kullanici duzenleyebilir).
// Ad, odeme formunun iki alanina bolunur; alan sinirlari (32/32) asilmaz.
$profil  = kullanici_bilgisi();
$ad_parca = preg_split('/\s+/u', trim((string)($profil['adsoyad'] ?? '')), 2, PREG_SPLIT_NO_EMPTY);
$fm_isim    = mb_substr($ad_parca[0] ?? '', 0, 32, 'UTF-8');
$fm_soyisim = mb_substr($ad_parca[1] ?? '', 0, 32, 'UTF-8');
$fm_eposta  = mb_substr((string)($profil['eposta'] ?? ''), 0, 64, 'UTF-8');
$fm_telefon = mb_substr((string)($profil['telefon'] ?? ''), 0, 20, 'UTF-8');
$fm_il      = mb_substr((string)($profil['il'] ?? ''), 0, 64, 'UTF-8');
$fm_ilce    = mb_substr((string)($profil['ilce'] ?? ''), 0, 32, 'UTF-8');
$fm_adres   = mb_substr((string)($profil['adres'] ?? ''), 0, 500, 'UTF-8');
$fm_kurum   = '';
$fm_pk      = mb_substr((string)($profil['posta_kodu'] ?? ''), 0, 5, 'UTF-8');
$fm_odeme   = 'havale';
$fm_onay    = 0;

// Sunucu tarafindaki dogrulamadan gecmeyen gonderim: alan degerleri profil yerine
// oturumdaki snapshot'tan alinir, hata listesi de ayni yerde durur. Boylece
// odeme ekrani aynen kalir, kullanici yalnizca eksik alani duzeltir.
$hatalar = [];
$hatali_alanlar = [];
if (!empty($_SESSION['odeme_formu']) && is_array($_SESSION['odeme_formu'])) {
    $d = $_SESSION['odeme_formu']['degerler'] ?? [];
    $fm_isim    = (string)($d['isim'] ?? '');
    $fm_soyisim = (string)($d['soyisim'] ?? '');
    $fm_eposta  = (string)($d['eposta'] ?? '');
    $fm_telefon = (string)($d['telefon'] ?? '');
    $fm_il      = (string)($d['il'] ?? '');
    $fm_ilce    = (string)($d['ilce'] ?? '');
    $fm_adres   = (string)($d['adres'] ?? '');
    $fm_kurum   = (string)($d['kurum'] ?? '');
    $fm_pk      = (string)($d['postakodu'] ?? '');
    $fm_odeme   = (string)($d['odemesekli'] ?? '');
    $fm_onay    = !empty($d['onay']) ? 1 : 0;
    $hatalar    = (array)($_SESSION['odeme_formu']['hatalar'] ?? []);
    $hatali_alanlar = (array)($_SESSION['odeme_formu']['hatali_alanlar'] ?? []);
}

// Sunucudan donen her hata kendi alanini kirmiziya boyar; Bootstrap
// .invalid-feedback yalnizca .is-invalid (veya form.was-validated) ile gorunur.
$hatali = array_fill_keys($hatali_alanlar, true);
$gecersiz = static function (string $alan) use ($hatali): string {
    return isset($hatali[$alan]) ? ' is-invalid' : '';
};

// Sepette DB'de kalmamis urun kalmadiysa odeme ekranina gecit yok
if (!$sepetOzeti['kalemler']) {
    redirect('sepetim.php');
}

// Toplamlari istemciden degil, sunucuda DB fiyatlarindan yeniden hesapla
$urun_tutari  = $sepetOzeti['ara_toplam'];
$kargo_ucreti = $sepetOzeti['kargo'];
$toplam       = $sepetOzeti['toplam'];

include "header.php";
?>
<div class="container mt-4 mb-5">
    <h1 class="baslik mb-4"><svg class="ik me-2" aria-hidden="true"><use href="#lk-credit-card"></use></svg>Ödeme Bilgileri</h1>

    <!-- Sunucu dogrulamasindan donen hatalar: odeme ekrani terk edilmez, ayni
         form hatali alanlarla birlikte tekrar basilir. Istemci tarafi da ayni
         kutuyu kullanir (content/js/odeme-form.js). -->
    <div id="odemeHataOzeti" class="alert alert-danger <?= $hatalar ? '' : 'd-none' ?>" role="alert">
        <div class="d-flex align-items-center gap-2 mb-1">
            <svg class="ik" aria-hidden="true"><use href="#lk-alert-triangle"></use></svg>
            <strong>Lutfen isaretli alanlari kontrol edin.</strong>
        </div>
        <ul class="mb-0 ps-4">
            <?php foreach ($hatalar as $hata): ?>
                <li><?= esc($hata) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="row">
        <!-- Sol Taraf: Teslimat ve Ödeme Bilgileri -->
        <div class="col-lg-8 mb-4">
            <form name="form" method="post" action="siparis_onay.php" id="odemeForm" novalidate>
                <?= csrf_alan() ?>
                <?= siparis_token_alani() ?>
                <!-- Teslimat Adresi -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-body">
                        <h4 class="baslik mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-truck"></use></svg>Teslimat Adresi</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="isim" class="form-label">İsim *</label>
                                    <input type="text" id="isim" name="isim" class="form-control<?= $gecersiz('isim') ?>" placeholder="Adınız"
                                           required minlength="2" maxlength="32" value="<?= esc($fm_isim) ?>">
                                    <div class="invalid-feedback">Lutfen adinizi girin (2-32 karakter).</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soyisim" class="form-label">Soyisim *</label>
                                    <input type="text" id="soyisim" name="soyisim" class="form-control<?= $gecersiz('soyisim') ?>" placeholder="Soyadınız"
                                           required minlength="2" maxlength="32" value="<?= esc($fm_soyisim) ?>">
                                    <div class="invalid-feedback">Lutfen soyadinizi girin (2-32 karakter).</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="il" class="form-label">İl *</label>
                                    <select class="form-select<?= $gecersiz('il') ?>" id="il" name="il" required>
                                        <?= il_secenekleri($fm_il) ?>
                                    </select>
                                    <div class="invalid-feedback">Il secimi zorunludur.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ilce" class="form-label">İlçe *</label>
                                    <input type="text" id="ilce" name="ilce" class="form-control<?= $gecersiz('ilce') ?>" placeholder="İlçe adı"
                                           required minlength="3" maxlength="32" value="<?= esc($fm_ilce) ?>">
                                    <div class="invalid-feedback">Ilce bilgisi zorunludur (3-32 karakter).</div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="adres" class="form-label">Adres *</label>
                                    <textarea id="adres" name="adres" class="form-control<?= $gecersiz('adres') ?>" 
                                              placeholder="Mahalle, Cadde/Sokak, Kapı no..." 
                                              rows="3" required minlength="10" maxlength="500"><?= esc($fm_adres) ?></textarea>
                                    <div class="invalid-feedback">Acik adres zorunludur, en az 10 en fazla 500 karakter olmalidir.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kurum" class="form-label">Kurum/Şirket</label>
                                    <input type="text" id="kurum" name="kurum" class="form-control<?= $gecersiz('kurum') ?>" placeholder="Kurum veya Şirket adı"
                                           maxlength="128" value="<?= esc($fm_kurum) ?>">
                                    <div class="invalid-feedback">Kurum/şirket adi en fazla 128 karakter olabilir.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="postakodu" class="form-label">Posta Kodu</label>
                                    <input type="text" id="postakodu" name="postakodu" class="form-control<?= $gecersiz('postakodu') ?>" placeholder="Beş haneli posta kodu"
                                           maxlength="5" pattern="[0-9]{5}" title="Beş haneli posta kodu" value="<?= esc($fm_pk) ?>">
                                    <div class="invalid-feedback">Posta kodu yazilacaksa 5 haneli olmalidir.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="eposta" class="form-label">E-posta Adresi *</label>
                                    <input type="email" id="eposta" name="eposta" class="form-control<?= $gecersiz('eposta') ?>" 
                                           placeholder="ornek@email.com" required maxlength="64" value="<?= esc($fm_eposta) ?>">
                                    <div class="invalid-feedback">Gecerli bir e-posta adresi girin (en fazla 64 karakter).</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefon" class="form-label">Telefon Numarası *</label>
                                    <input type="tel" id="telefon" name="telefon" class="form-control<?= $gecersiz('telefon') ?>" 
                                           placeholder="5XX XXX XX XX" required minlength="10" maxlength="20"
                                           pattern="[0-9+() -]+" title="Rakam, +, (), bosluk ve - kullanabilirsiniz" value="<?= esc($fm_telefon) ?>">
                                    <div class="invalid-feedback">Telefon numarasi zorunludur ve en az 10 haneli olmalidir.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ödeme Seçenekleri -->
                <div class="card shadow-sm">
                    <div class="card-header bg-body">
                        <h4 class="baslik mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-credit-card"></use></svg>Ödeme Seçenekleri</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check mb-3">
                                <input class="form-check-input<?= $gecersiz('odemesekli') ?>" type="radio" name="odemesekli" 
                                       id="havale" value="havale" required <?= $fm_odeme === 'havale' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="havale">
                                    Havale/EFT
                                </label>
                                <p class="text-muted ps-4 mt-1 mb-0">
                                    Havale ve EFT ödemelerinizi anlaşmalı banka hesaplarına üç gün içinde yapabilirsiniz.
                                </p>
                                <div class="invalid-feedback">Gecerli bir odeme yontemi secin.</div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="odemesekli" 
                                       id="kart" value="kart" <?= $fm_odeme === 'kart' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="kart">
                                    Banka/Kredi Kartı
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="odemesekli" 
                                       id="kapida" value="kapida" <?= $fm_odeme === 'kapida' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="kapida">
                                    Kapıda Ödeme
                                </label>
                            </div>
                        </div>
                        
                        <!-- Satış Sözleşmesi -->
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="onay" 
                                   id="onay" required <?= $fm_onay ? 'checked' : '' ?>>
                            <label class="form-check-label" for="onay">
                                <a href="#" class="text-decoration-none">Satış Sözleşmesi</a>'ni okudum ve onaylıyorum.
                            </label>
                            <div class="invalid-feedback">
                                Satış sözleşmesini onaylamadan devam edemezsiniz.
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sağ Taraf: Sipariş Özeti -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-body">
                    <h4 class="baslik mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-receipt"></use></svg>Sipariş Özeti</h4>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td>Ürün Tutarı</td>
                                <td class="text-end"><?php echo para($urun_tutari); ?>&#8378;</td>
                            </tr>
                            <tr>
                                <td>Kargo Ücreti</td>
                                <td class="text-end">
                                    <?php if($kargo_ucreti == 0): ?>
                                        <span class="text-success">Ücretsiz</span>
                                    <?php else: ?>
                                        <?php echo para($kargo_ucreti); ?>&#8378;
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold">Toplam</td>
                                <td class="text-end fw-bold fs-5 text-success">
                                    <?php echo para($toplam); ?>&#8378;
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" form="odemeForm" class="btn btn-success btn-lg py-3">
                            <svg class="ik me-2" aria-hidden="true"><use href="#lk-lock"></use></svg>Ödemeyi Tamamla
                        </button>
                        
                        <a href="sepetim.php" class="btn btn-outline-secondary">
                            <svg class="ik me-2" aria-hidden="true"><use href="#lk-arrow-left"></use></svg>Sepete Dön
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <svg class="ik me-1" aria-hidden="true"><use href="#lk-shield-check"></use></svg>
                            256-bit SSL ile güvenli ödeme
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
// Snapshot tek atimlidir: alan degerleri yukarida basilir basilmaz oturumdan
// cikarilir, boylece geri/durdur ile sayfaya donen kullanici eski hatalari ile
// karsilasmaz. Sepet ve CSRF tokeni bundan etkilenmez.
unset($_SESSION['odeme_formu']);
?>

<!-- Form Validation -->
<?= varlik_js('content/js/odeme-form.js') ?>

<?php include "footer.php"; ?>
