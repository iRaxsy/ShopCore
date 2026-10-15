<?php
/**
 * Kullanici hesap sayfas: profil/adres bilgileri ve sifre degistirme.
 *
 * Iki ayri POST formu `form` alaniyla (profil / sifre) ayrisilir. Her ikisi de
 * CSRF + kullanici_zorunlu() ile korunur ve guncelleme hedefi daima OTURUMDAKI
 * kullanici id'sidir; POST'tan gelen id/kimlik asla kullanilmaz.
 *
 * Kayit bulunamazsa (hesap silinmis) kullanici_zorunlu() girise yonlendirir,
 * bu yuzden asagida $profil her zaman doludur.
 */
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();
kullanici_zorunlu();

$con    = db();
$profil = kullanici_bilgisi();
$id     = kullanici_id();

$hatalar    = [];
$gonderilen = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();
    $form = (string)($_POST['form'] ?? '');

    if ($form === 'profil') {
        $adsoyad    = temiz_metin($_POST['adsoyad'] ?? '');
        $telefon    = preg_replace('/[^\d+() -]/u', '', (string)($_POST['telefon'] ?? ''));
        $il         = temiz_metin($_POST['il'] ?? '');
        $ilce       = temiz_metin($_POST['ilce'] ?? '');
        $adres      = trim((string)($_POST['adres'] ?? ''));
        $posta_kodu = preg_replace('/[^\d]/', '', (string)($_POST['posta_kodu'] ?? ''));

        $gonderilen = compact('adsoyad', 'telefon', 'il', 'ilce', 'adres', 'posta_kodu');

        // Alan sinirlari odeme formuyla (satinal.php / siparis_onay.php) birebir
        // ayni: profilinden dolan form oldugu gibi gecmeli.
        if (mb_strlen($adsoyad) < 3) {
            $hatalar[] = 'Ad soyad en az 3 karakter olmalıdır.';
        } elseif (mb_strlen($adsoyad) > 64) {
            $hatalar[] = 'Ad soyad en fazla 64 karakter olabilir.';
        }
        if (strlen($telefon) > 20) {
            $hatalar[] = 'Telefon numarası en fazla 20 karakter olabilir.';
        } elseif ($telefon !== '' && strlen(preg_replace('/\D/', '', $telefon)) < 10) {
            $hatalar[] = 'Telefon numarası en az 10 haneli olmalıdır.';
        }
        // Il, odeme formundaki secenegin bir parcas olmali (siparis_illeri())
        if ($il !== '' && !in_array($il, siparis_illeri(), true)) {
            $hatalar[] = 'Geçerli bir il seçin.';
        }
        if (mb_strlen($ilce) > 32) {
            $hatalar[] = 'İlçe bilgisi en fazla 32 karakter olabilir.';
        }
        if (mb_strlen($adres) > 500) {
            $hatalar[] = 'Açık adres en fazla 500 karakter olabilir.';
        } elseif ($adres !== '' && mb_strlen($adres) < 10) {
            $hatalar[] = 'Adres en az 10 karakter olmalıdır (veya boş bırakın).';
        }
        if (strlen($posta_kodu) > 5) {
            $hatalar[] = 'Posta kodu en fazla 5 haneli olabilir.';
        }

        if (!$hatalar) {
            try {
                $stmt = $con->prepare('UPDATE kullanicilar SET adsoyad = ?, telefon = ?, il = ?, ilce = ?, adres = ?, posta_kodu = ? WHERE id = ?');
                $stmt->execute([
                    header_temizle($adsoyad),
                    header_temizle($telefon),
                    header_temizle($il),
                    header_temizle($ilce),
                    header_temizle($adres),
                    $posta_kodu !== '' ? $posta_kodu : null,
                    $id,
                ]);
                flash('basari', 'Hesap bilgileriniz güncellendi.');
                redirect('hesabim.php');
            } catch (PDOException $e) {
                error_log('hesabim profil: ' . $e->getMessage());
                $hatalar[] = 'Bilgiler kaydedilemedi, lütfen tekrar deneyin.';
            }
        }
    } elseif ($form === 'sifre') {
        $mevcut  = (string)($_POST['mevcut_sifre'] ?? '');
        $yeni    = (string)($_POST['yeni_sifre'] ?? '');
        $yeni2   = (string)($_POST['yeni_sifre_tekrar'] ?? '');

        if ($mevcut === '' || $yeni === '' || $yeni2 === '') {
            $hatalar[] = 'Lütfen tüm şifre alanlarını doldurun.';
        }
        if ($yeni !== '' && mb_strlen($yeni) < 8) {
            $hatalar[] = 'Yeni şifre en az 8 karakter olmalıdır.';
        }
        if (mb_strlen($yeni) > 72) {
            $hatalar[] = 'Yeni şifre en fazla 72 karakter olabilir.';
        }
        if ($yeni !== '' && $yeni2 !== '' && !hash_equals($yeni, $yeni2)) {
            $hatalar[] = 'Yeni şifre ve tekrarı birbiriyle aynı değil.';
        }

        if (!$hatalar) {
            try {
                $stmt = $con->prepare('SELECT sifre FROM kullanicilar WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $kayit = $stmt->fetch(PDO::FETCH_ASSOC);

                // Mevcut sifre dogrulanmadan hicbir guncelleme yapilmaz; mesaj
                // bilincli olarak jenerik (hash hakkinda bilgi verilmez).
                if (!$kayit || !password_verify($mevcut, $kayit['sifre'])) {
                    $hatalar[] = 'Mevcut şifreniz doğru değil.';
                } else {
                    $up = $con->prepare('UPDATE kullanicilar SET sifre = ? WHERE id = ?');
                    $up->execute([password_hash($yeni, PASSWORD_BCRYPT), $id]);

                    flash('basari', 'Şifreniz değiştirildi.');
                    redirect('hesabim.php');
                }
            } catch (PDOException $e) {
                error_log('hesabim sifre: ' . $e->getMessage());
                $hatalar[] = 'Şifre değiştirilemedi, lütfen tekrar deneyin.';
            }
        }
    } else {
        // Beklenmeyen form adi: hicbir islem yapilmaz
        $hatalar[] = 'Geçersiz form.';
    }
}

// Form degerleri: hatali gonderimde kullaniciyin yazdigi, aksi halde profil
$deger = function ($alan) use ($gonderilen, $profil) {
    return array_key_exists($alan, $gonderilen) ? $gonderilen[$alan] : (string)($profil[$alan] ?? '');
};

include 'header.php';
?>
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="baslik mb-0">
            <svg class="ik me-2" aria-hidden="true"><use href="#lk-circle-user"></use></svg>Hesabım
        </h1>
        <a href="siparislerim.php" class="btn btn-outline-primary">
            <svg class="ik me-2" aria-hidden="true"><use href="#lk-receipt"></use></svg>Siparişlerim
        </a>
    </div>

    <?php if ($hatalar): ?>
        <div class="alert alert-danger" role="alert">
            <svg class="ik me-2" aria-hidden="true"><use href="#lk-alert-circle"></use></svg>
            <?= esc(implode(' ', $hatalar)) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sol: hesap ve teslimat bilgileri -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body">
                    <h2 class="baslik h5 mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-map-pin"></use></svg>Hesap ve Teslimat Bilgileri</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= esc($_SERVER['PHP_SELF']) ?>">
                        <?= csrf_alan() ?>
                        <input type="hidden" name="form" value="profil">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="eposta" class="form-label">E-posta</label>
                                <input type="email" id="eposta" class="form-control" value="<?= esc($profil['eposta']) ?>" readonly>
                                <small class="form-text text-muted">Giriş kimliğinizdir, değiştirilemez.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="adsoyad" class="form-label">Ad Soyad *</label>
                                <input type="text" id="adsoyad" name="adsoyad" class="form-control"
                                       maxlength="64" value="<?= esc($deger('adsoyad')) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" id="telefon" name="telefon" class="form-control"
                                       placeholder="5XX XXX XX XX" maxlength="20" value="<?= esc($deger('telefon')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="posta_kodu" class="form-label">Posta Kodu</label>
                                <input type="text" id="posta_kodu" name="posta_kodu" class="form-control"
                                       placeholder="Beş haneli posta kodu" maxlength="5" value="<?= esc($deger('posta_kodu')) ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="il" class="form-label">İl</label>
                                <select class="form-select" id="il" name="il">
                                    <?= il_secenekleri($deger('il')) ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ilce" class="form-label">İlçe</label>
                                <input type="text" id="ilce" name="ilce" class="form-control"
                                       placeholder="İlçe adı" maxlength="32" value="<?= esc($deger('ilce')) ?>">
                            </div>

                            <div class="col-12">
                                <label for="adres" class="form-label">Adres</label>
                                <textarea id="adres" name="adres" class="form-control" rows="3"
                                          placeholder="Mahalle, Cadde/Sokak, Kapı no..."
                                          maxlength="500"><?= esc($deger('adres')) ?></textarea>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success">
                                <svg class="ik me-2" aria-hidden="true"><use href="#lk-check"></use></svg>Bilgileri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sag: sifre degistir -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body">
                    <h2 class="baslik h5 mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-lock"></use></svg>Şifre Değiştir</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= esc($_SERVER['PHP_SELF']) ?>">
                        <?= csrf_alan() ?>
                        <input type="hidden" name="form" value="sifre">

                        <div class="mb-3">
                            <label for="mevcut_sifre" class="form-label">Mevcut Şifre *</label>
                            <input type="password" id="mevcut_sifre" name="mevcut_sifre" class="form-control"
                                   maxlength="72" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label for="yeni_sifre" class="form-label">Yeni Şifre *</label>
                            <input type="password" id="yeni_sifre" name="yeni_sifre" class="form-control"
                                   placeholder="En az 8 karakter" maxlength="72" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (tekrar) *</label>
                            <input type="password" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" class="form-control"
                                   maxlength="72" required autocomplete="new-password">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <svg class="ik me-2" aria-hidden="true"><use href="#lk-refresh-cw"></use></svg>Şifreyi Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
