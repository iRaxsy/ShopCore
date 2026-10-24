<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik (sorgu ve guncelleme) cikti baslamadan ONCE calisir
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    admin_hata('HATA: Geçersiz ID bilgisi!');
}

$id = (int)$_GET['id'];

try {
    $stmt = $con->prepare("SELECT id, adsoyad, eposta, telefon, il, ilce, adres, sifre FROM kullanicilar WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$kayit) {
        admin_hata('HATA: Kayıt bulunamadı.', '', 404);
    }
} catch (PDOException $e) {
    admin_hata(db_hata_mesaji($e, 'HATA: Kayit okunurken bir sorun olustu.'), $e->getMessage(), 500);
}

// DB'de ham deger durur; kacirma yalnizca cikti noktalarinda esc() ile yapilir
$adsoyad = $kayit['adsoyad'];
$eposta  = $kayit['eposta'];
$telefon = $kayit['telefon'];
$il      = $kayit['il'];
$ilce    = $kayit['ilce'];
$adres   = $kayit['adres'];
// Sifre (hash) forma ASLA basilmez; bos birakilarsa degistirilmez
$sifre_g = '';
$sifre_kaldi = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    $adsoyad_g = temiz_metin($_POST['adsoyad'] ?? '');
    $eposta_g  = kullanici_eposta_normalize($_POST['eposta'] ?? '');
    $telefon_g = preg_replace('/[^\d+() -]/u', '', (string)($_POST['telefon'] ?? ''));
    $il_g      = temiz_metin($_POST['il'] ?? '');
    $ilce_g    = temiz_metin($_POST['ilce'] ?? '');
    $adres_g   = trim((string)($_POST['adres'] ?? ''));
    $sifre_g   = (string)($_POST['sifre'] ?? '');

    $hatalar = [];

    // Alan sinirlari kayit/Hesabim kurallariyla birebir ayni (bkz. ekle.php).
    if ($adsoyad_g === '' || $eposta_g === '') {
        $hatalar[] = 'Ad soyad ve e-posta alanları zorunludur.';
    }
    if (mb_strlen($adsoyad_g) < 3) {
        $hatalar[] = 'Ad soyad en az 3 karakter olmalıdır.';
    } elseif (mb_strlen($adsoyad_g) > 64) {
        $hatalar[] = 'Ad soyad en fazla 64 karakter olabilir.';
    }
    if ($eposta_g !== '' && !gecerli_email($eposta_g)) {
        $hatalar[] = 'Geçerli bir e-posta adresi girin.';
    }
    if (mb_strlen($eposta_g) > 64) {
        $hatalar[] = 'E-posta adresi en fazla 64 karakter olabilir.';
    }
    if (strlen($telefon_g) > 20) {
        $hatalar[] = 'Telefon numarası en fazla 20 karakter olabilir.';
    } elseif ($telefon_g !== '' && strlen(preg_replace('/\D/', '', $telefon_g)) < 10) {
        $hatalar[] = 'Telefon numarası en az 10 haneli olmalıdır.';
    }
    if ($il_g !== '' && !in_array($il_g, siparis_illeri(), true)) {
        $hatalar[] = 'Geçerli bir il seçin.';
    }
    if (mb_strlen($ilce_g) > 32) {
        $hatalar[] = 'İlçe bilgisi en fazla 32 karakter olabilir.';
    }
    if (mb_strlen($adres_g) > 500) {
        $hatalar[] = 'Açık adres en fazla 500 karakter olabilir.';
    } elseif ($adres_g !== '' && mb_strlen($adres_g) < 10) {
        $hatalar[] = 'Adres en az 10 karakter olmalıdır (veya boş bırakın).';
    }
    // Sifre opsiyonel: bos -> mevcut hash korunur
    if ($sifre_g !== '' && mb_strlen($sifre_g) < 8) {
        $hatalar[] = 'Yeni şifre en az 8 karakter olmalıdır.';
    }
    if (mb_strlen($sifre_g) > 72) {
        $hatalar[] = 'Yeni şifre en fazla 72 karakter olabilir.';
    }

    if (!$hatalar && $eposta_g !== $kayit['eposta']) {
        try {
            $kontrol = $con->prepare('SELECT id FROM kullanicilar WHERE eposta = ? AND id <> ? LIMIT 1');
            $kontrol->execute([$eposta_g, $id]);
            if ($kontrol->fetch()) {
                $hatalar[] = 'Bu e-posta adresiyle kayıtlı başka bir kullanıcı var.';
            }
        } catch (PDOException $e) {
            error_log('kullanici/duzelt kontrol: ' . $e->getMessage());
            $hatalar[] = 'Kayıt sırasında bir hata oluştu.';
        }
    }

    if ($hatalar) {
        flash('uyari', implode(' ', $hatalar));
        // Hatali gonderimde kullaniciyin yazdigi geri basilir
        $adsoyad = $adsoyad_g;
        $eposta  = $eposta_g;
        $telefon = $telefon_g;
        $il      = $il_g;
        $ilce    = $ilce_g;
        $adres   = $adres_g;
    } else {
        $sifre_kaldi = $sifre_g === '';
        try {
            if (!$sifre_kaldi) {
                $stmt = $con->prepare('UPDATE kullanicilar SET adsoyad = ?, eposta = ?, telefon = ?, il = ?, ilce = ?, adres = ?, sifre = ? WHERE id = ?');
                $ok = $stmt->execute([
                    header_temizle($adsoyad_g),
                    header_temizle($eposta_g),
                    header_temizle($telefon_g),
                    header_temizle($il_g),
                    header_temizle($ilce_g),
                    header_temizle($adres_g),
                    password_hash($sifre_g, PASSWORD_BCRYPT),
                    $id,
                ]);
            } else {
                $stmt = $con->prepare('UPDATE kullanicilar SET adsoyad = ?, eposta = ?, telefon = ?, il = ?, ilce = ?, adres = ? WHERE id = ?');
                $ok = $stmt->execute([
                    header_temizle($adsoyad_g),
                    header_temizle($eposta_g),
                    header_temizle($telefon_g),
                    header_temizle($il_g),
                    header_temizle($ilce_g),
                    header_temizle($adres_g),
                    $id,
                ]);
            }

            if ($ok) {
                flash('basari', 'Kullanıcı başarıyla güncellendi.');
                // redirect() hedefinin basina APP_BASE_URL eklenir; bu yuzden
                // klasor icinden bile tam yol ('admin/kullanici/liste.php') verilir
                redirect('admin/kullanici/liste.php');
            }
            flash('hata', 'Kullanıcı güncellenemedi.');
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                // uk_kullanici_eposta: yarista baska bir istek ayni e-postayi aldi
                flash('uyari', 'Bu e-posta adresiyle kayıtlı başka bir kullanıcı var.');
            } else {
                error_log('kullanici/duzelt: ' . $e->getMessage());
                flash('hata', 'Güncelleme sirasinda bir hata olustu.');
            }
        }
    }
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Kullanıcı Güncelleme</h1>
    </div>
    
    <div class="form-container">
        <!-- ID Bilgi Kutusu -->
        <div class="id-info-box">
            <span class="id-label">GÜNCELLENEN KULLANICI ID:</span>
            <span class="id-value">#<?= $id ?></span>
        </div>
        
        <h2 class="form-title">Kullanıcı Bilgilerini Düzenle</h2>
        
        <form id="uyeGuncelleForm" action="<?= esc($_SERVER["PHP_SELF"] . "?id={$id}") ?>" method="post" novalidate>
            <?= csrf_alan() ?>
            <!-- Ad ve Soyad -->
            <div class="form-group">
                <label for="adsoyad" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-user"></use></svg>
                    Ad ve Soyad
                </label>
                <input type="text" id="adsoyad" name="adsoyad" class="form-control" required
                       minlength="3" maxlength="64" value="<?= esc($adsoyad) ?>"
                       placeholder="Örnek: Ahmet Yılmaz" />
            </div>
            
            <!-- E-posta -->
            <div class="form-group">
                <label for="eposta" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-mail"></use></svg>
                    E-posta Adresi
                </label>
                <input type="email" id="eposta" name="eposta" class="form-control" required
                       maxlength="64" value="<?= esc($eposta) ?>"
                       placeholder="ornek@email.com" />
                <small class="help-block">Kullanici giriş kimliğidir; değiştirirlerse yeni adresle giriş yaparlar</small>
            </div>
            
            <!-- Telefon -->
            <div class="form-group">
                <label for="telefon" class="form-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-phone"></use></svg>
                    Telefon
                </label>
                <input type="tel" id="telefon" name="telefon" class="form-control"
                       minlength="10" maxlength="20" value="<?= esc($telefon) ?>"
                       placeholder="5XX XXX XX XX" />
            </div>
            
            <!-- Il / Ilce -->
            <div class="form-group">
                <label for="il" class="form-label">İl</label>
                <select class="form-select" id="il" name="il">
                    <?= il_secenekleri($il) ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="ilce" class="form-label">İlçe</label>
                <input type="text" id="ilce" name="ilce" class="form-control"
                       maxlength="32" value="<?= esc($ilce) ?>"
                       placeholder="İlçe adı" />
            </div>
            
            <!-- Adres -->
            <div class="form-group">
                <label for="adres" class="form-label">Açık Adres</label>
                <textarea id="adres" name="adres" class="form-control" rows="3"
                          minlength="10" maxlength="500"
                          placeholder="Mahalle, Cadde/Sokak, Kapı no..."><?= esc($adres) ?></textarea>
            </div>
            
            <!-- Şifre -->
            <div class="form-group">
                <label for="sifre" class="form-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-lock"></use></svg>
                    Şifre
                </label>
                <div class="password-wrapper">
                    <input type="password" id="sifre" name="sifre" class="form-control" autocomplete="new-password"
                           data-opsiyonel="1"
                           value=""
                           placeholder="Değiştirmek için yazın" />
                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Şifreyi göster/gizle">
                        <svg class="ik" aria-hidden="true"><use href="#lk-eye"></use></svg>
                    </button>
                </div>
                <small class="help-block">Boş bırakırsanız mevcut şifre değişmez. Değiştirmek isterseniz en az 8 karakter girin.</small>
            </div>
            
            <!-- İşlem Butonları -->
            <div class="button-container">
                <button type="submit" class='btn btn-primary'>
                    <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Güncelle
                </button>
                
                <a href='liste.php' class='btn btn-danger'>
                    <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg> Kullanıcı Listesi
                </a>
            </div>
        </form>
    </div>
</div>

<?php include "../footer.php"; ?>

<!-- Bootstrap bundle + SweetAlert2 + admin.js footer.php üzerinden yükleniyor -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uyeGuncelleForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = form.querySelector('#sifre');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            // Lucide sprite'ta gorsel <use href="#lk-..."> ile secilir; <i> etiketi yok.
            const ikon = this.querySelector('.ik use');
            if (ikon) {
                ikon.setAttribute('href', type === 'text' ? '#lk-eye-off' : '#lk-eye');
            }
        });
    }
});
</script>
