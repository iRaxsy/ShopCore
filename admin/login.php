<?php
require_once __DIR__ . '/../includes/bootstrap.php';
app_session_start();
$con = db();

// GET ile cikis (`?cikis=1`) kaldirilmistir: cikis artik admin/header.php
// icindeki POST + CSRF formundan yapilir (GET cikisi CSRF/saldirisi ile
// zorla cikisa ve log sizmasina aciktir).

$hataMesaji = "";

// Kaba kuvvet siniri: ayni oturumda bu sayidan fazla hatali giris istegi
// reddedilir (429). Deger dusuk tutulur; demo/yonetim paneli icin yeterli.
$deneme_limiti = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    $deneme = (int)($_SESSION['_giris_deneme'] ?? 0);
    if ($deneme >= $deneme_limiti) {
        error_log('login: deneme siniri asildi (oturumdaki hatali deneme: ' . $deneme . ')');
        http_response_code(429);
        sleep(3);
        sendJsonResponse(false, 'Cok fazla hatali giris denemesi. Lutfen bir sure sonra tekrar deneyin.');
    }

    $kadi = trim($_POST["kadi"] ?? '');
    $ksifre = trim($_POST["ksifre"] ?? '');

    if (empty($kadi) || empty($ksifre)) {
        $hataMesaji = "Kullanıcı adı ve şifre alanları boş bırakılamaz!";
        sendJsonResponse(false, $hataMesaji);
    }

    try {
        // Sadece kullanici adina gore getir; sifre dogramasi PHP'de yapilir
        $stmt = $con->prepare("SELECT id, kadi, sifre FROM yetkililer WHERE kadi = ? LIMIT 1");
        $stmt->execute([$kadi]);
        $yetkili = $stmt->fetch(PDO::FETCH_ASSOC);

        $gecerli = false;
        if ($yetkili) {
            $kayitli = $yetkili['sifre'];
            if (password_get_info($kayitli)['algo'] !== null && strlen($kayitli) >= 60 && strpos($kayitli, '$2') === 0) {
                // bcrypt/argon hash
                $gecerli = password_verify($ksifre, $kayitli);
            } else {
                // Eski duz metin satiri (lazy upgrade): bir kezlik karsilastirma
                $gecerli = hash_equals($kayitli, $ksifre);
                if ($gecerli) {
                    // Basarili giriste hash'e gecir
                    $yeni = password_hash($ksifre, PASSWORD_BCRYPT);
                    $up = $con->prepare("UPDATE yetkililer SET sifre = ? WHERE id = ?");
                    $up->execute([$yeni, $yetkili['id']]);
                }
            }
        }

        if ($gecerli) {
            // Basarili giriste sayac sifirlanir, sonra yeni oturum id'si alinir
            unset($_SESSION['_giris_deneme']);
            // Session fixation korumasi
            session_regenerate_id(true);
            $_SESSION["loginkey"] = $yetkili['kadi'];
            $_SESSION["yetkili_id"] = (int)$yetkili['id'];
            csrf_yenile();
            sendJsonResponse(true, 'index.php');
        } else {
            $_SESSION['_giris_deneme'] = $deneme + 1;
            // Log satirina yeni karakter sizmasin (kadi kullanici girdisi)
            $log_kadi = preg_replace('/[\r\n\0]/', '', $kadi);
            error_log(sprintf(
                'login: hatali giris denemesi (kadi=%s, deneme=%d, ip=%s)',
                $log_kadi,
                $_SESSION['_giris_deneme'],
                $_SERVER['REMOTE_ADDR'] ?? '?'
            ));
            $hataMesaji = "Kullanıcı adı veya şifre hatalı!";
            sendJsonResponse(false, $hataMesaji);
        }
    } catch (PDOException $e) {
        error_log('login: ' . $e->getMessage());
        $hataMesaji = "Sistem hatası oluştu!";
        sendJsonResponse(false, $hataMesaji);
    }
}

function sendJsonResponse($success, $message) {
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true, 'redirect' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => $message]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?= tema_head() ?>
    <title>Giriş | ShopCore Admin</title>
    <!-- Bootstrap 5 CSS + SweetAlert2 CSS: yerel kopyalar (admin ile ayni dosya) -->
    <?= varlik_css(APP_VARLIK_BOOTSTRAP_CSS) ?>
    <!-- Ikonlar: harici kutuphane yok, yerel Lucide sprite'i (includes/ikonlar.php) -->
    <!-- .ik bileseni style.css icinde tanimli; admin sayfalarindaki gibi yuklenir -->
    <?= varlik_css('content/css/style.css') ?>
    <?= varlik_css(APP_VARLIK_SWAL_CSS) ?>
    <!-- Admin stili (login kurallari body.login-page altinda izole) -->
    <?= varlik_css('content/css/admin-style.css') ?>
</head>
<body class="login-page">
<?php require __DIR__ . '/../includes/ikonlar.php'; ?>
    <div class="login-wrapper">
        <div class="tema-ust">
            <button type="button" class="tema-btn" aria-label="Tema: Açık" title="Tema: Açık">
                <svg class="ik" aria-hidden="true"><use href="#lk-sun"></use></svg>
            </button>
        </div>
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-icon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-shield-check"></use></svg>
                </div>
                <h1 class="login-baslik">Shop<strong>Core</strong></h1>
                <p class="login-alt-baslik">Yönetim Paneli</p>
                <p class="login-aciklama">Sisteme erişmek için giriş yapın</p>
            </div>
            
            <form id="loginForm" method="POST">
                <?= csrf_alan() ?>
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-icon" aria-hidden="true">
                            <svg class="ik" aria-hidden="true"><use href="#lk-user"></use></svg>
                        </span>
                        <input type="text" class="form-control" id="kadi" name="kadi" 
                               placeholder="Kullanıcı adı" value="<?= esc($_POST['kadi'] ?? '') ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-icon" aria-hidden="true">
                            <svg class="ik" aria-hidden="true"><use href="#lk-lock"></use></svg>
                        </span>
                        <input type="password" class="form-control" id="ksifre" name="ksifre" 
                               placeholder="Şifre" required>
                        <button type="button" class="password-toggle" id="togglePassword" 
                                aria-label="Şifreyi göster/gizle">
                            <svg class="ik" aria-hidden="true"><use href="#lk-eye"></use></svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login" id="girisBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="loadingSpinner"></span>
                    <span id="btnText">Giriş Yap</span>
                </button>
            </form>
            
            <div class="login-alt">
                <p class="text-muted mb-1">&copy; <?= date('Y') ?> ShopCore</p>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS (yerel kopya; CSS'i yukarida link'lendi) -->
    <?= varlik_js(APP_VARLIK_SWAL_JS) ?>
    
    <!-- Acik/koyu tema toggle motoru -->
    <?= varlik_js('content/js/tema.js') ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('ksifre');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.innerHTML = type === 'password' ? 
                '<svg class="ik" aria-hidden="true"><use href="#lk-eye"></use></svg>' : 
                '<svg class="ik" aria-hidden="true"><use href="#lk-eye-off"></use></svg>';
        });
        
        const loginForm = document.getElementById('loginForm');
        const girisBtn = document.getElementById('girisBtn');
        const btnText = document.getElementById('btnText');
        const loadingSpinner = document.getElementById('loadingSpinner');
        
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const kadi = document.getElementById('kadi').value.trim();
            const ksifre = document.getElementById('ksifre').value.trim();
            
            if (!kadi || !ksifre) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Uyarı!',
                    text: 'Kullanıcı adı ve şifre alanları boş bırakılamaz!',
                    confirmButtonColor: '#198754'
                });
                return;
            }
            
            girisBtn.disabled = true;
            loadingSpinner.classList.remove('d-none');
            btnText.textContent = 'Giriş yapılıyor...';
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch('<?= esc($_SERVER["PHP_SELF"]) ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Başarılı!',
                        text: 'Giriş yapılıyor...',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.location.href = data.redirect;
                } else {
                    throw new Error(data.message);
                }
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: error.message || 'Giriş işlemi başarısız!',
                    confirmButtonColor: '#198754'
                });
                
                resetForm();
            }
        });
        
        function resetForm() {
            girisBtn.disabled = false;
            loadingSpinner.classList.add('d-none');
            btnText.textContent = 'Giriş Yap';
            document.getElementById('ksifre').value = '';
        }
        
        <?php if (!empty($hataMesaji)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: '<?= json_encode($hataMesaji, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>',
            confirmButtonColor: '#198754'
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>
