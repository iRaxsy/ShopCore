<?php
require_once __DIR__ . '/includes/bootstrap.php';

app_session_start();
csrf_dogrula(); // AJAX X-CSRF-Token header'i ile dogrulanir

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? trim((string)$_POST['id']) : "";
$adet = isset($_POST['adet']) ? (int)$_POST['adet'] : null;

if ($id === '' || !is_numeric($id) || (int)$id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ürün ID bilgisi eksik veya geçersiz']);
    exit;
}
$id = (int)$id;

if (!isset($_SESSION['sepet'])) {
    $_SESSION['sepet'] = array();
}

if (!array_key_exists($id, $_SESSION['sepet'])) {
    echo json_encode(['success' => false, 'message' => 'Ürün sepette bulunamadı']);
    exit;
}

if ($adet !== null) {
    if ($adet < 1) {
        $adet = 1;
    } elseif ($adet > 99) {
        $adet = 99;
    }

    // Stok siniri: sepetteki adet stoktan fazla olamaz (siparis asamasinda
    // ISLEM icinde tekrar dogrulanir; burada amac kullaniyi erken uyarmak)
    $stokSorgu = db()->prepare("SELECT urunadi, stok FROM urunler WHERE id = ? LIMIT 1");
    $stokSorgu->execute([$id]);
    $urun = $stokSorgu->fetch(PDO::FETCH_ASSOC);
    if (!$urun) {
        unset($_SESSION['sepet'][$id]);
        echo json_encode(['success' => false, 'message' => 'Ürün artık satışta değil, sepetten çıkarıldı.']);
        exit;
    }
    $stok = (int)$urun['stok'];
    if ($stok <= 0) {
        echo json_encode(['success' => false, 'message' => $urun['urunadi'] . ' stoklarda tükendi.']);
        exit;
    }
    if ($adet > $stok) {
        $adet = $stok;
        $uyariMesaji = 'Stok sınırı: en fazla ' . $stok . ' adet eklenebilir.';
    }

    $_SESSION['sepet'][$id] = array('adet' => $adet);
    $yanit = [
        'success' => true,
        'message' => 'Ürün adedi güncellendi',
        'count' => count($_SESSION['sepet']),
        'adet' => $adet
    ];
    if (isset($uyariMesaji)) {
        $yanit['message'] = $uyariMesaji;
    }
} else {
    unset($_SESSION['sepet'][$id]);
    $yanit = [
        'success' => true,
        'message' => 'Ürün sepetten silindi',
        'count' => count($_SESSION['sepet'])
    ];
}

session_write_close();

echo json_encode($yanit);
