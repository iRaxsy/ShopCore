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

    // Siparis donem kaydi KORUNUR: siparisler.kullanici_id FK'si ON DELETE SET
    // NULL tanimlidir (sql/shopcore.sql :: CREATE TABLE siparisler), yani hesap
    // silinse bile siparisin ad/eposta/adres kopyasi ve siparis kalemleri
    // yerinde kalir; siparis yalnizca "misafir siparisi"ne doner. Panel erisimi
    // bu tablodan BAGIMSIZDIR (yetkililer), bu yuzden buradan bir hesap silmek
    // admin hesaplarina dokunmaz.
    $stmt = $con->prepare("DELETE FROM kullanicilar WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo "silindi";
    } else {
        echo "silinemedi";
    }

} catch (PDOException $e) {
    error_log('kullanici sil: ' . $e->getMessage());
    echo "HATA: Islem sirasinda sunucu hatasi olustu.";
}
