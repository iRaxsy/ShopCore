<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// Kimlik dogrulama + CSRF
app_session_start();
admin_zorunlu();
csrf_dogrula();

// Degisiklik yapan uc yalniz POST kabul eder (GET loglara/gecmise sizabilir)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'HATA: Bu islem yalniz POST ile calistirilir.';
    exit;
}

$con = db();

try {
    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo 'HATA: Gecersiz Id bilgisi.';
        exit;
    }

    // id=1: kurulumdan gelen ANA ADMIN hesabi. Bu hesap hicbir kosulda
    // silinemez; baska bir yetkili (id != 1) oturum acsa bile panel ici
    // silme reddedilir. Kimlik/sifre guncellemesi serbesttir (bkz.
    // admin/yetkili/duzelt.php) - koruma yalniz DELETE ucuna uygulanir.
    // sql/shopcore.sql :: INSERT INTO yetkililer satirindaki id=1 tohum
    // kaydi bu invariant'e dayanir; DB'de elle silinirse tek-care olarak
    // shopcore.sql'i bastan import et (tum veri silinir) ya da ayni INSERT'i
    // elle calistir.
    if ($id === 1) {
        echo "korunuyor";
        exit;
    }

    // Kendini silme korumasi (login artik yetkili_id set eder)
    if (admin_id() !== null && admin_id() === $id) {
        echo "kendini_silme";
        exit;
    }

    $stmt = $con->prepare("DELETE FROM yetkililer WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo "silindi";
    } else {
        echo "silinemedi";
    }

} catch (PDOException $e) {
    error_log('yetkili sil: ' . $e->getMessage());
    echo "HATA: Islem sirasinda sunucu hatasi olustu.";
}
