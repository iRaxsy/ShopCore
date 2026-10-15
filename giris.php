<?php
/**
 * Kullanici giris sayfas. Admin panelinin girisiyle (admin/login.php) HICBIR
 * kesisimi yoktur: farkli tablo (kullanicilar), farkli oturum anahtari
 * (kullanici_id vs. yetkili_id) ve farkli dosya.
 *
 * Bu sayfa GET ile de acilir (form buradadir), yazici islem ayni adrese POST
 * ile gelir. Yonlendirme/mantik header basilamadan ONCE kosar.
 */
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();
$con = db();

// Zaten giris yapmis kullaniciyi giris ekraninda tutmaya gerek yok
if (kullanici_girisi_var_mi()) {
    redirect('index.php');
}

// Kaba kuvvet siniri: ayni oturumda bu sayidan fazla hatali giris denemesi
// islenmez (admin/login.php ile ayni esik).
$deneme_limiti = 5;

// `geri` hedefi beyaz listeden suzulur; sepetten gelen kullanici geri doner.
$geri = geri_hedefi('sepetim.php');

$hatalar = [];
$eski_eposta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    if (kullanici_giris_sayaci('_kullanici_giris_deneme') >= $deneme_limiti) {
        error_log('giris: kullanici deneme siniri asildi (oturum: ' . kullanici_giris_sayaci('_kullanici_giris_deneme') . ')');
        $hatalar[] = 'Çok fazla hatalı giriş denemesi. Lütfen bir süre sonra tekrar deneyin.';
    } else {
        $eposta = kullanici_eposta_normalize($_POST['eposta'] ?? '');
        $sifre  = (string)($_POST['sifre'] ?? '');

        $eski_eposta = $eposta;

        if ($eposta === '' || $sifre === '') {
            $hatalar[] = 'E-posta ve şifre alanları boş bırakılamaz.';
        } elseif (!gecerli_email($eposta)) {
            $hatalar[] = 'Geçerli bir e-posta adresi girin.';
        } else {
            try {
                $stmt = $con->prepare('SELECT id, sifre FROM kullanicilar WHERE eposta = ? LIMIT 1');
                $stmt->execute([$eposta]);
                $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);

                // Basarisiz durumda "hesap yok" / "sifre yanlis" ayrimi
                // bilincli olarak yapilmaz (kullanici varligi sizintisi yok).
                if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
                    unset($_SESSION['_kullanici_giris_deneme']);

                    // Session fixation korumasi: giris sonrasi yeni oturum id'si
                    session_regenerate_id(true);
                    $_SESSION['kullanici_id'] = (int)$kullanici['id'];
                    csrf_yenile();

                    redirect($geri);
                }

                $deneme = kullanici_giris_sayaci('_kullanici_giris_deneme', true);
                // Log satirina kullanici girdisinden satir sonu sizmasin
                $log_eposta = header_temizle($eposta);
                error_log(sprintf(
                    'giris: hatali kullanici girisi (eposta=%s, deneme=%d, ip=%s)',
                    $log_eposta,
                    $deneme,
                    $_SERVER['REMOTE_ADDR'] ?? '?'
                ));
                $hatalar[] = 'E-posta veya şifre hatalı!';
            } catch (PDOException $e) {
                error_log('giris: ' . $e->getMessage());
                $hatalar[] = 'Sistem hatası oluştu, lütfen tekrar deneyin.';
            }
        }
    }
}

include 'header.php';
?>
<div class="container mt-4 mb-5 auth-sayfa">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="card shadow-sm auth-kart">
                <div class="card-header bg-body">
                    <h1 class="baslik mb-0 auth-baslik">
                        <svg class="ik me-2" aria-hidden="true"><use href="#lk-log-in"></use></svg>Giriş Yap
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

                        <div class="mb-3">
                            <label for="eposta" class="form-label">E-posta Adresi *</label>
                            <input type="email" id="eposta" name="eposta" class="form-control"
                                   placeholder="ornek@email.com" maxlength="64"
                                   value="<?= esc($eski_eposta) ?>" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="sifre" class="form-label">Şifre *</label>
                            <input type="password" id="sifre" name="sifre" class="form-control"
                                   placeholder="Şifreniz" maxlength="72" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg py-3">
                                <svg class="ik me-2" aria-hidden="true"><use href="#lk-log-in"></use></svg>Giriş Yap
                            </button>
                        </div>
                    </form>

                    <hr>

                    <p class="text-center mb-0 auth-alt-not">
                        Hesabınız yok mu?
                        <a href="<?= url('kayit.php', ['geri' => $geri]) ?>" class="text-decoration-none">Hemen kayıt olun</a>
                    </p>
                    <p class="text-center text-muted small mb-0 mt-2">
                        Sipariş verebilmek için giriş yapmanız gerekiyor; sepetiniz giriş sonrası olduğu gibi durur.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
