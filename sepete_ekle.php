<?php
require_once __DIR__ . '/includes/bootstrap.php';

app_session_start();
csrf_dogrula(); // AJAX X-CSRF-Token header'i ile dogrulanir

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? trim((string)$_POST['id']) : "";
$adet = isset($_POST['adet']) ? (int)$_POST['adet'] : 1;

if ($id === '' || !is_numeric($id) || (int)$id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ürün ID bilgisi eksik veya geçersiz']);
    exit;
}
$id = (int)$id;

// Urun DB'de gercekten var mi? Olmayan id ile sepet kalemi olusturulmaz
$stmt = db()->prepare("SELECT id, stok FROM urunler WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$urun = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$urun) {
    echo json_encode(['success' => false, 'message' => 'Ürün bulunamadı']);
    exit;
}

// Stok kontrolu: sepette beklenen adet ile sepete eklenecek adet, stoktan
// fazla olamaz (satinal asamasinda tekrar ve ISLEM icinde dogrulanir)
$stok = (int)$urun['stok'];
if ($stok <= 0) {
    echo json_encode(['success' => false, 'message' => 'Bu ürün şu anda stoklarda yok.']);
    exit;
}

// Adeti guvenli araliga cek
if ($adet < 1) {
    $adet = 1;
} elseif ($adet > 99) {
    $adet = 99;
}

if (!isset($_SESSION['sepet'])) {
    $_SESSION['sepet'] = array();
}

if (array_key_exists($id, $_SESSION['sepet'])) {
    // Ayni urun tekrar eklenince adet artar (sepet tek satir tutar)
    $yeniAdet = min(99, $stok, (int)($_SESSION['sepet'][$id]['adet'] ?? 0) + $adet);
    $_SESSION['sepet'][$id] = array('adet' => $yeniAdet);
    $mesaj = [
        'success' => true,
        'message' => $yeniAdet >= $stok
            ? 'Ürün adedi güncellendi; stoka işaret edilen ' . $yeniAdet . ' adetten fazlası alınamaz.'
            : 'Ürün adedi sepette güncellendi (toplam ' . $yeniAdet . ')',
        'count'   => count($_SESSION['sepet']),
        'adet'    => $yeniAdet,
    ];
} else {
    $adet = min($adet, $stok);
    $_SESSION['sepet'][$id] = array('adet' => $adet);
    $mesaj = ['success' => true, 'message' => 'Ürün sepete eklendi', 'count' => count($_SESSION['sepet']), 'adet' => $adet];
}

// Oturum kilidini birak
session_write_close();

echo json_encode($mesaj);
