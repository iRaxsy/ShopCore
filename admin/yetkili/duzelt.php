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
    $stmt = $con->prepare("SELECT id, adsoyad, kadi, sifre FROM yetkililer WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$kayit) {
        admin_hata('HATA: Kayıt bulunamadı.', '', 404);
    }
    
    // DB'de ham deger durur; kacirma yalnizca cikti noktalarinda esc() ile yapilir
    $adsoyad = $kayit['adsoyad'];
    $kadi = $kayit['kadi'];
    // Sifre (artik hash) forma ASLA basilmez; bos birakilarsa degistirilmez
    $sifre = '';
    
} catch (PDOException $e) {
    admin_hata('HATA: Kayit okunurken bir sorun olustu.', $e->getMessage(), 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();
    $adsoyad_g = trim($_POST['adsoyad'] ?? '');
    $kadi_g = trim($_POST['kadi'] ?? '');
    $sifre_g = (string)($_POST['sifre'] ?? '');

    if ($adsoyad_g === '' || $kadi_g === '') {
        flash('uyari', 'Lütfen ad-soyad ve kullanıcı adı alanlarını doldurun.');
    } elseif ($sifre_g !== '' && mb_strlen($sifre_g) < 8) {
        flash('uyari', 'Şifre en az 8 karakter olmalıdır.');
    } else {
        $adsoyad = temiz_metin($adsoyad_g);
        $kadi = temiz_metin($kadi_g);
        try {
            if ($sifre_g !== '') {
                // Sifre degistiriliyor -> hash'le
                $sifre_hash = password_hash($sifre_g, PASSWORD_BCRYPT);
                $stmt = $con->prepare("UPDATE yetkililer SET adsoyad=?, kadi=?, sifre=? WHERE id = ?");
                $ok = $stmt->execute([$adsoyad, $kadi, $sifre_hash, $id]);
            } else {
                // Sifre bos -> mevcut sifreyi koru
                $stmt = $con->prepare("UPDATE yetkililer SET adsoyad=?, kadi=? WHERE id = ?");
                $ok = $stmt->execute([$adsoyad, $kadi, $id]);
            }

            if ($ok) {
                flash('basari', 'Yetkili başarıyla güncellendi.');
            } else {
                flash('hata', 'Yetkili güncellenemedi.');
            }
        } catch (PDOException $e) {
            error_log('yetkili duzelt: ' . $e->getMessage());
            flash('hata', 'Güncelleme sirasinda bir hata olustu.');
        }
    }
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Yetkili Güncelleme</h1>
    </div>
    
    <div class="form-container">
        <!-- ID Bilgi Kutusu -->
        <div class="id-info-box">
            <span class="id-label">GÜNCELLENEN YETKILI ID:</span>
            <span class="id-value">#<?= $id ?></span>
        </div>
        
        <h2 class="form-title">Yetkili Bilgilerini Düzenle</h2>
        
        <form id="yetkiliGuncelleForm" action="<?= esc($_SERVER["PHP_SELF"] . "?id={$id}") ?>" method="post">
            <?= csrf_alan() ?>
            <!-- Ad ve Soyad -->
            <div class="form-group">
                <label for="adsoyad" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-user"></use></svg>
                    Ad ve Soyad
                </label>
                <input type='text' id="adsoyad" name='adsoyad' class='form-control' required 
                       value="<?= esc($adsoyad) ?>"
                       placeholder="Örnek: Ahmet Yılmaz" />
            </div>
            
            <!-- Kullanıcı Adı -->
            <div class="form-group">
                <label for="kadi" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-circle-user"></use></svg>
                    Kullanıcı Adı
                </label>
                <input type='text' id="kadi" name='kadi' class='form-control' required 
                       value="<?= esc($kadi) ?>"
                       placeholder="Örnek: ahmetyilmaz" />
                <small class="help-block">Sistem girişi için kullanılacak</small>
            </div>
            
            <!-- Şifre -->
            <div class="form-group">
                <label for="sifre" class="form-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-lock"></use></svg>
                    Şifre
                </label>
                <div class="password-wrapper">
                    <input type='password' id="sifre" name='sifre' class='form-control' autocomplete="new-password"
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
                    <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg> Yetkili Listesi
                </a>
            </div>
        </form>
    </div>
</div>

<?php include "../footer.php"; ?>

<!-- Bootstrap bundle + SweetAlert2 + admin.js footer.php üzerinden yükleniyor -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('yetkiliGuncelleForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = form.querySelector('#sifre');
    const userId = <?= $id ?>;
    
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
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const adsoyad = formData.get('adsoyad');
        const kadi = formData.get('kadi');
        const sifre = formData.get('sifre');
        
        // Sunucu kurali: sifre opsiyonel, bos birakilirsa mevcut hash korunur
        if (!adsoyad || !kadi) {
            Swal.fire({
                title: "Uyarı!",
                text: "Lütfen ad-soyad ve kullanıcı adı alanlarını doldurun!",
                icon: "warning",
                confirmButtonText: "Tamam"
            });
            return;
        }
        
        if (sifre !== '' && sifre.length < 8) {
            Swal.fire({
                title: "Uyarı!",
                text: "Şifre en az 8 karakter olmalıdır!",
                icon: "warning",
                confirmButtonText: "Tamam"
            });
            return;
        }
        
        if (kadi.length < 3) {
            Swal.fire({
                title: "Uyarı!",
                text: "Kullanıcı adı en az 3 karakter olmalıdır!",
                icon: "warning",
                confirmButtonText: "Tamam"
            });
            return;
        }
        
        if (adsoyad.length < 3) {
            Swal.fire({
                title: "Uyarı!",
                text: "Ad soyad en az 3 karakter olmalıdır!",
                icon: "warning",
                confirmButtonText: "Tamam"
            });
            return;
        }
        
        Swal.fire({
            title: "Emin misiniz?",
            text: "Yetkili #" + userId + " için bilgiler güncellenecek: " + adsoyad,
            icon: "info",
            showCancelButton: true,
            confirmButtonColor: "#0d3b66",
            cancelButtonColor: "#dc3545",
            confirmButtonText: "Evet, güncelle!",
            cancelButtonText: "Hayır"
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
    
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        updateInputStyle(input);
        
        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--ad-lacivert-metin)';
            this.style.boxShadow = '0 0 0 3px rgba(13, 59, 102, 0.18)';
        });
        
        input.addEventListener('blur', function() {
            updateInputStyle(this);
        });
    });
    
    function updateInputStyle(input) {
        // Bos birakilabilen alan (sifre) bos haliyle hatali gorunmez
        if (!input.value.trim() && input.dataset.opsiyonel === '1') {
            input.style.borderColor = '';
            input.style.boxShadow = '';
            return;
        }
        if (input.value.trim()) {
            input.style.borderColor = 'var(--ad-yesil-metin)';
            input.style.boxShadow = '0 0 0 3px rgba(25, 135, 84, 0.2)';
        } else {
            input.style.borderColor = 'var(--ad-danger-metin)';
            input.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.2)';
        }
    }
});
</script>
