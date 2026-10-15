<?php
/**
 * Kullanici kayit sayfas. Kayit `kullanicilar` tablosuna yazilir; admin giris
 * tablosu (yetkililer) ile iliskisi yoktur, boylece yeni bir hesap asla
 * yonetim paneline erisim hakki dogurmaz.
 *
 * Basarili kayit ayni zamanda giris anlamina gelir: oturum `kullanici_id` ile
 * doldurulur ve kullanici (varsa) sepetine/odeme adimina geri doner.
 */
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();
$con = db();

if (kullanici_girisi_var_mi()) {
    redirect('index.php');
}

// Kaba kuvvet / spam siniri (giris sayfasindaki sayactan ayri bir alan)
$deneme_limiti = 5;

$geri = geri_hedefi('index.php');

$hatalar = [];
$eski = ['adsoyad' => '', 'eposta' => '', 'telefon' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    if (kullanici_giris_sayaci('_kayit_deneme') >= $deneme_limiti) {
        error_log('kayit: kullanici kayit deneme siniri asildi');
        $hatalar[] = 'Çok fazla hatalı deneme. Lütfen bir süre sonra tekrar deneyin.';
    } else {
        $adsoyad = temiz_metin($_POST['adsoyad'] ?? '');
        $eposta  = kullanici_eposta_normalize($_POST['eposta'] ?? '');
        $telefon = preg_replace('/[^\d+() -]/u', '', (string)($_POST['telefon'] ?? ''));
        $sifre   = (string)($_POST['sifre'] ?? '');
        $sifre2  = (string)($_POST['sifre_tekrar'] ?? '');

        $eski = ['adsoyad' => $adsoyad, 'eposta' => $eposta, 'telefon' => $telefon];

        if ($adsoyad === '' || $eposta === '' || $telefon === '' || $sifre === '' || $sifre2 === '') {
            $hatalar[] = 'Lütfen tüm alanları doldurun.';
        }

        // Ust sinirlar iki katmanli: formdaki maxlength atlanabilir, bu yuzden
        // sunucuda da reddedilir. Degerler kullanicilar kolonlarinin altinda
        // secildi (sql/shopcore.sql :: CREATE TABLE kullanicilar).
        if (mb_strlen($adsoyad) < 3) {
            $hatalar[] = 'Ad soyad en az 3 karakter olmalıdır.';
        } elseif (mb_strlen($adsoyad) > 64) {
            $hatalar[] = 'Ad soyad en fazla 64 karakter olabilir.';
        }
        if ($eposta !== '' && !gecerli_email($eposta)) {
            $hatalar[] = 'Geçerli bir e-posta adresi girin.';
        }
        if (mb_strlen($eposta) > 64) {
            $hatalar[] = 'E-posta adresi en fazla 64 karakter olabilir.';
        }
        if (strlen($telefon) > 20) {
            $hatalar[] = 'Telefon numarası en fazla 20 karakter olabilir.';
        } elseif ($telefon !== '' && strlen(preg_replace('/\D/', '', $telefon)) < 10) {
            // Sadece rakam sayisi kontrol edilir: "+90 (5xx)..." gibi bicimler
            // kabul edilir, ama 10 haneli bir abone numarasi karsiligi olmali.
            $hatalar[] = 'Telefon numarası en az 10 haneli olmalıdır.';
        }
        if ($sifre !== '' && mb_strlen($sifre) < 8) {
            $hatalar[] = 'Şifre en az 8 karakter olmalıdır.';
        }
        // bcrypt'in 72 baytluk tavani: asilirsa hash sessizce kirpilir, bu yuzden
        // girdi basinda reddedilir.
        if (mb_strlen($sifre) > 72) {
            $hatalar[] = 'Şifre en fazla 72 karakter olabilir.';
        }
        if ($sifre !== '' && $sifre2 !== '' && !hash_equals($sifre, $sifre2)) {
            $hatalar[] = 'Girdiğiniz şifreler birbiriyle aynı değil.';
        }

        // Mükerrer hesap: kod tarafi kontrolü + DB'deki UNIQUE (yaris kosulunda
        // son sozu DB soyler, asagida 23000 yakalanir).
        if (!$hatalar) {
            try {
                $kontrol = $con->prepare('SELECT id FROM kullanicilar WHERE eposta = ? LIMIT 1');
                $kontrol->execute([$eposta]);
                if ($kontrol->fetch()) {
                    $hatalar[] = 'Bu e-posta adresiyle kayıtlı bir hesap zaten var. Giriş yapabilirsiniz.';
                }
            } catch (PDOException $e) {
                error_log('kayit kontrol: ' . $e->getMessage());
                $hatalar[] = 'Sistem hatası oluştu, lütfen tekrar deneyin.';
            }
        }

        if (!$hatalar) {
            try {
                $stmt = $con->prepare('INSERT INTO kullanicilar SET adsoyad = ?, eposta = ?, telefon = ?, sifre = ?');
                $stmt->execute([
                    header_temizle($adsoyad),
                    header_temizle($eposta),
                    header_temizle($telefon),
                    password_hash($sifre, PASSWORD_BCRYPT),
                ]);

                // Kayit = giris: fixation korumasi + yeni oturum anahtari
                unset($_SESSION['_kayit_deneme']);
                session_regenerate_id(true);
                $_SESSION['kullanici_id'] = (int)$con->lastInsertId();
                csrf_yenile();

                flash('basari', 'Hesabınız oluşturuldu. Sepetinize dönüp siparişinizi tamamlayabilirsiniz.');
                redirect($geri);
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    // uk_kullanici_eposta: ayni anda iki ayni kayit denemesi
                    $hatalar[] = 'Bu e-posta adresiyle kayıtlı bir hesap zaten var. Giriş yapabilirsiniz.';
                } else {
                    error_log('kayit: ' . $e->getMessage());
                    $hatalar[] = 'Hesap oluşturulamadı. Lütfen tekrar deneyin.';
                }
            }
        }

        if ($hatalar) {
            kullanici_giris_sayaci('_kayit_deneme', true);
        }
    }
}

include 'header.php';
?>
<div class="container mt-4 mb-5 auth-sayfa">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-6">
            <div class="card shadow-sm auth-kart">
                <div class="card-header bg-body">
                    <h1 class="baslik mb-0 auth-baslik">
                        <svg class="ik me-2" aria-hidden="true"><use href="#lk-circle-user"></use></svg>Kayıt Ol
                    </h1>
                </div>
                <div class="card-body">

                    <?php if ($hatalar): ?>
                        <div class="alert alert-danger" role="alert">
                            <svg class="ik me-2" aria-hidden="true"><use href="#lk-alert-circle"></use></svg>
                            <?= esc(implode(' ', $hatalar)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= esc($_SERVER['PHP_SELF']) ?>" novalidate>
                        <?= csrf_alan() ?>
                        <input type="hidden" name="geri" value="<?= esc($geri) ?>">

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="adsoyad" class="form-label">Ad Soyad *</label>
                                <input type="text" id="adsoyad" name="adsoyad" class="form-control"
                                       placeholder="Örnek: Ahmet Yılmaz" maxlength="64"
                                       value="<?= esc($eski['adsoyad']) ?>" required autofocus>
                            </div>

                            <div class="col-md-6">
                                <label for="eposta" class="form-label">E-posta Adresi *</label>
                                <input type="email" id="eposta" name="eposta" class="form-control"
                                       placeholder="ornek@email.com" maxlength="64"
                                       value="<?= esc($eski['eposta']) ?>" required>
                                <small class="form-text text-muted">Giriş kimliğiniz budur.</small>
                            </div>

                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon *</label>
                                <input type="tel" id="telefon" name="telefon" class="form-control"
                                       placeholder="5XX XXX XX XX" maxlength="20"
                                       value="<?= esc($eski['telefon']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="sifre" class="form-label">Şifre *</label>
                                <input type="password" id="sifre" name="sifre" class="form-control"
                                       placeholder="En az 8 karakter" maxlength="72" required
                                       autocomplete="new-password">
                            </div>

                            <div class="col-md-6">
                                <label for="sifre_tekrar" class="form-label">Şifre (tekrar) *</label>
                                <input type="password" id="sifre_tekrar" name="sifre_tekrar" class="form-control"
                                       placeholder="Şifrenizi tekrar girin" maxlength="72" required
                                       autocomplete="new-password">
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg py-3">
                                <svg class="ik me-2" aria-hidden="true"><use href="#lk-check"></use></svg>Hesabı Oluştur
                            </button>
                        </div>
                    </form>

                    <hr>

                    <p class="text-center mb-0 auth-alt-not">
                        Zaten hesabınız var mı?
                        <a href="<?= url('giris.php', ['geri' => $geri]) ?>" class="text-decoration-none">Giriş yapın</a>
                    </p>
                    <p class="text-center text-muted small mb-0 mt-2">
                        Adres ve sipariş bilgilerinizi daha sonra Hesabım sayfasından tamamlayabilirsiniz.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
