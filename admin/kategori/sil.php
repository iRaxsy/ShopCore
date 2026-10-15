<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// Kimlik dogrulama + CSRF (yazici islem). Cagri noktalarindaki fetch,
// admin.js'in ekledigi X-CSRF-Token basligini tasir. Silme bir degisiklik
// oldugu icin istek yalniz POST kabul eder: GET istekleri tarayici gecmisine,
// onbellegine ve sunucu loglarina sizabilir.
app_session_start();
admin_zorunlu();
csrf_dogrula();

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

    $stmt = $con->prepare("SELECT COUNT(*) as urun_sayisi FROM urunler WHERE kategori_id = ?");
    $stmt->execute([$id]);
    $sonuc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sonuc['urun_sayisi'] > 0) {
        echo "urun_var";
        exit;
    }

    $stmt = $con->prepare("DELETE FROM kategoriler WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo "silindi";
    } else {
        echo "silinemedi";
    }

} catch (PDOException $e) {
    error_log('kategori sil: ' . $e->getMessage());
    echo "HATA: Islem sirasinda sunucu hatasi olustu.";
}
