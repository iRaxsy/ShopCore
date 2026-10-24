<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Basarili kayit sonrasi form temiz basar (kategori akisi gibi); hatali
// gonderimde false kalir, kullaniciyin yazdigi degerler korunur.
$kaydedildi = false;

// Tum mantik cikti baslamadan ONCE calisir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    $adsoyad = temiz_metin($_POST['adsoyad'] ?? '');
    $eposta  = kullanici_eposta_normalize($_POST['eposta'] ?? '');
    $telefon = preg_replace('/[^\d+() -]/u', '', (string)($_POST['telefon'] ?? ''));
    $il      = temiz_metin($_POST['il'] ?? '');
    $ilce    = temiz_metin($_POST['ilce'] ?? '');
    $adres   = trim((string)($_POST['adres'] ?? ''));
    $sifre_g = (string)($_POST['sifre'] ?? '');

    $hatalar = [];

    // Alan sinirlari site kurallariyla (kayit.php / hesabim.php) BIREBIR ayni
    // secildi: panelde olusan hesap, kullaniciya giris formundan ve Hesabim
    // sayfasindan oldugu gibi gecmeli. Aksi halde "kayitli ama girilemez" hesap
    // uretiriz (giris formunun maxlength'i dusuk kalir).
    if ($adsoyad === '' || $eposta === '' || $sifre_g === '') {
        $hatalar[] = 'Ad soyad, e-posta ve şifre alanları zorunludur.';
    }
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
        $hatalar[] = 'Telefon numarası en az 10 haneli olmalıdır.';
    }
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
    if ($sifre_g !== '' && mb_strlen($sifre_g) < 8) {
        $hatalar[] = 'Şifre en az 8 karakter olmalıdır.';
    }
    // bcrypt'in 72 baytluk tavani: asilirsa hash sessizce kirpilir.
    if (mb_strlen($sifre_g) > 72) {
        $hatalar[] = 'Şifre en fazla 72 karakter olabilir.';
    }

    if (!$hatalar) {
        try {
            $kontrol = $con->prepare('SELECT id FROM kullanicilar WHERE eposta = ? LIMIT 1');
            $kontrol->execute([$eposta]);
            if ($kontrol->fetch()) {
                $hatalar[] = 'Bu e-posta adresiyle kayıtlı bir kullanıcı zaten var.';
            }
        } catch (PDOException $e) {
            error_log('kullanici/ekle kontrol: ' . $e->getMessage());
            $hatalar[] = 'Kayıt sırasında bir hata oluştu.';
        }
    }

    if ($hatalar) {
        flash('uyari', implode(' ', $hatalar));
    } else {
        try {
            $stmt = $con->prepare('INSERT INTO kullanicilar SET adsoyad = ?, eposta = ?, telefon = ?, il = ?, ilce = ?, adres = ?, sifre = ?');
            $stmt->execute([
                header_temizle($adsoyad),
                header_temizle($eposta),
                header_temizle($telefon),
                header_temizle($il),
                header_temizle($ilce),
                header_temizle($adres),
                password_hash($sifre_g, PASSWORD_BCRYPT),
            ]);
            flash('basari', 'Kullanıcı başarıyla kaydedildi.');
            // Kategori akisi gibi: liste.php'ye yonlendirme yok, ayni sayfada
            // kalinarak bant gosterilir ve form sifirlanir.
            $kaydedildi = true;
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                // uk_kullanici_eposta: ayni anda iki ayni kayit denemesi
                flash('uyari', 'Bu e-posta adresiyle kayıtlı bir kullanıcı zaten var.');
            } else {
                error_log('kullanici/ekle: ' . $e->getMessage());
                flash('hata', 'Kayıt sırasında bir hata oluştu.');
            }
        }
    }
}

include "../header.php";

// Hatali gonderimde kullaniciyin yazdigi kaybolmasin (sifre alan bilerek
// geri basilmez); GET isteginde $_POST bos oldugu icin alanlar bos kalir.
$deger = function ($alan) use ($kaydedildi) {
    if ($kaydedildi) {
        return '';
    }
    return esc((string)($_POST[$alan] ?? ''));
};
?>


<div class="container">
    <div class="page-header">
        <h1>Kullanıcı Ekle</h1>
    </div>
    
    <div class="form-container">
        <h2 class="form-title">Kullanıcı Bilgileri</h2>
        
        <form id="uyeEkleForm" action="<?= esc($_SERVER["PHP_SELF"]) ?>" method="post" novalidate>
            <?= csrf_alan() ?>
            <!-- Ad ve Soyad -->
            <div class="form-group">
                <label for="adsoyad" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-user"></use></svg>
                    Ad ve Soyad
                </label>
                <input type="text" id="adsoyad" name="adsoyad" class="form-control" required
                       minlength="3" maxlength="64" placeholder="Örnek: Ahmet Yılmaz"
                       value="<?= $deger('adsoyad') ?>" />
            </div>
            
            <!-- E-posta -->
            <div class="form-group">
                <label for="eposta" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-mail"></use></svg>
                    E-posta Adresi
                </label>
                <input type="email" id="eposta" name="eposta" class="form-control" required
                       maxlength="64" placeholder="ornek@email.com"
                       value="<?= $deger('eposta') ?>" />
                <small class="help-block">Kullanici giriş kimliğidir; sistemde tekil olmalıdır</small>
            </div>
            
            <!-- Telefon -->
            <div class="form-group">
                <label for="telefon" class="form-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-phone"></use></svg>
                    Telefon
                </label>
                <input type="tel" id="telefon" name="telefon" class="form-control"
                       minlength="10" maxlength="20" placeholder="5XX XXX XX XX"
                       value="<?= $deger('telefon') ?>" />
            </div>
            
            <!-- Il / Ilce -->
            <div class="form-group">
                <label for="il" class="form-label">İl</label>
                <select class="form-select" id="il" name="il">
                    <?= il_secenekleri($kaydedildi ? '' : (string)($_POST['il'] ?? '')) ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="ilce" class="form-label">İlçe</label>
                <input type="text" id="ilce" name="ilce" class="form-control"
                       maxlength="32" placeholder="İlçe adı"
                       value="<?= $deger('ilce') ?>" />
            </div>
            
            <!-- Adres -->
            <div class="form-group">
                <label for="adres" class="form-label">Açık Adres</label>
                <textarea id="adres" name="adres" class="form-control" rows="3"
                          minlength="10" maxlength="500"
                          placeholder="Mahalle, Cadde/Sokak, Kapı no..."><?= $deger('adres') ?></textarea>
                <small class="help-block">Boş bırakılırsa kullanıcı ödeme adımında kendi adresini yazar</small>
            </div>
            
            <!-- Şifre -->
            <div class="form-group">
                <label for="sifre" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-lock"></use></svg>
                    Şifre
                </label>
                <input type="password" id="sifre" name="sifre" class="form-control" required
                       minlength="8" maxlength="72" placeholder="En az 8 karakter"
                       autocomplete="new-password" />
                <small class="help-block">Kullanıcı bu şifreyle giriş yapacak; site kaydıyla aynı kural: en az 8 karakter</small>
            </div>
            
            <!-- İşlem Butonları -->
            <div class="button-container">
                <button type="submit" class='btn btn-primary'>
                    <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Kaydet
                </button>
                
                <a href='liste.php' class='btn btn-danger'>
                    <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg> Kullanıcı Listesi
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('uyeEkleForm');
    if (!form) { return; }
    const kaydetBtn = form.querySelector('button[type="submit"]');
    const adsoyad = document.getElementById('adsoyad');
    const eposta  = document.getElementById('eposta');
    const sifre   = document.getElementById('sifre');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (adsoyad.value.trim().length < 3) {
            Swal.fire({ title: "Uyarı!", text: "Ad soyad en az 3 karakter olmalıdır!", icon: "warning", confirmButtonText: "Tamam" });
            adsoyad.focus(); return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(eposta.value.trim())) {
            Swal.fire({ title: "Uyarı!", text: "Geçerli bir e-posta adresi girin!", icon: "warning", confirmButtonText: "Tamam" });
            eposta.focus(); return;
        }
        if (sifre.value.length < 8) {
            Swal.fire({ title: "Uyarı!", text: "Şifre en az 8 karakter olmalıdır!", icon: "warning", confirmButtonText: "Tamam" });
            sifre.focus(); return;
        }

        Swal.fire({
            title: "Yeni Kullanıcı Ekle",
            text: "Bu kullanıcıyı kaydetmek istediğinizden emin misiniz?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Evet, Kaydet",
            cancelButtonText: "Hayır",
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                if (kaydetBtn) {
                    kaydetBtn.disabled = true;
                    kaydetBtn.innerHTML = '<svg class="ik ik-spin" aria-hidden="true"><use href="#lk-refresh-cw"></use></svg> Kaydediliyor...';
                }
                form.submit();
            }
        });
    });
});
</script>

<?php include "../footer.php"; ?>

<!-- Bootstrap bundle + SweetAlert2 + admin.js footer.php üzerinden yükleniyor -->
