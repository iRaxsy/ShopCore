<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik cikti baslamadan ONCE calisir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();
    $required_fields = ['adsoyad', 'kadi', 'sifre'];
    $has_error = false;
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $has_error = true;
            break;
        }
    }
    
    if (!$has_error) {
        $adsoyad = temiz_metin($_POST['adsoyad']);
        $kadi = temiz_metin($_POST['kadi']);
        $sifre_girdi = (string)$_POST['sifre'];
        
        if (mb_strlen($sifre_girdi, 'UTF-8') >= 8) {
            try {
                $sifre = password_hash($sifre_girdi, PASSWORD_BCRYPT);
                $stmt = $con->prepare("INSERT INTO yetkililer SET adsoyad=?, kadi=?, sifre=?");
                
                if ($stmt->execute([$adsoyad, $kadi, $sifre])) {
                    flash('basari', 'Yetkili başarıyla kaydedildi.');
                } else {
                    flash('hata', 'Yetkili kaydedilemedi.');
                }
            } catch (PDOException $e) {
                error_log('yetkili/ekle: ' . $e->getMessage());
                flash('hata', 'Kayıt sırasında bir hata oluştu.');
            }
        } else {
            flash('uyari', 'Şifre en az 8 karakter olmalıdır.');
        }
    } else {
        flash('uyari', 'Lütfen tüm zorunlu alanları doldurun.');
    }
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Yetkili Ekle</h1>
    </div>
    
    <div class="form-container">
        <h2 class="form-title">Yetkili Bilgileri</h2>
        
        <form id="yetkiliEkleForm" action="<?= esc($_SERVER["PHP_SELF"]) ?>" method="post">
            <?= csrf_alan() ?>
            <!-- Ad ve Soyad -->
            <div class="form-group">
                <label for="adsoyad" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-user"></use></svg>
                    Ad ve Soyad
                </label>
                <input type='text' id="adsoyad" name='adsoyad' class='form-control' required 
                       placeholder="Örnek: Ahmet Yılmaz" />
            </div>
            
            <!-- Kullanıcı Adı -->
            <div class="form-group">
                <label for="kadi" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-circle-user"></use></svg>
                    Kullanıcı Adı
                </label>
                <input type='text' id="kadi" name='kadi' class='form-control' required 
                       placeholder="Örnek: ahmetyilmaz" />
                <small class="help-block">Sistem girişi için kullanılacak</small>
            </div>
            
            <!-- Şifre -->
            <div class="form-group">
                <label for="sifre" class="form-label required-label">
                    <svg class="ik" aria-hidden="true"><use href="#lk-lock"></use></svg>
                    Şifre
                </label>
                <input type='password' id="sifre" name='sifre' class='form-control' required 
                       placeholder="En az 8 karakter" />
                <small class="help-block">Minimum 8 karakter olmalıdır</small>
            </div>
            
            <!-- İşlem Butonları -->
            <div class="button-container">
                <button type="submit" class='btn btn-primary'>
                    <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Kaydet
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
    const form = document.getElementById('yetkiliEkleForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const adsoyad = formData.get('adsoyad');
        const kadi = formData.get('kadi');
        const sifre = formData.get('sifre');
        
        if (!adsoyad || !kadi || !sifre) {
            Swal.fire({
                title: "Uyarı!",
                text: "Lütfen tüm zorunlu alanları doldurun!",
                icon: "warning",
                confirmButtonText: "Tamam"
            });
            return;
        }
        
        if (sifre.length < 8) {
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
            text: "Yeni yetkili kaydedilecek: " + adsoyad,
            icon: "info",
            showCancelButton: true,
            confirmButtonColor: "#0d3b66",
            cancelButtonColor: "#dc3545",
            confirmButtonText: "Evet, kaydet!",
            cancelButtonText: "Hayır"
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
    
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--ad-lacivert-metin)';
            this.style.boxShadow = '0 0 0 3px rgba(13, 59, 102, 0.18)';
        });
        
        input.addEventListener('blur', function() {
            if (this.value.trim()) {
                this.style.borderColor = 'var(--ad-yesil-metin)';
                this.style.boxShadow = '0 0 0 3px rgba(25, 135, 84, 0.2)';
            } else {
                this.style.borderColor = 'var(--ad-danger-metin)';
                this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.2)';
            }
        });
    });
});
</script>
