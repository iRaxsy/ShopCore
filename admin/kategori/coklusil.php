<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

app_session_start();
admin_zorunlu();
csrf_dogrula();

$con = db();
header('Content-Type: application/json');

// Degisiklik yapan uc yalniz POST kabul eder (GET loglara/gecmise sizabilir)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Bu islem yalniz POST ile calistirilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (empty($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Silinecek kategori seçilmedi.']);
        exit;
    }

    $idler = array_values(array_filter(array_map('intval', (array)$_POST['id']), function ($id) {
        return $id > 0;
    }));
    $hatalar = [];

    if (empty($idler)) {
        echo json_encode(['success' => false, 'message' => 'Geçerli kategori seçilmedi.']);
        exit;
    }

    $yer_tutucu = implode(',', array_fill(0, count($idler), '?'));

    // DONGU-DEGINA SORGU: urunu olan kategoriler tek sorguda bulunur (N+1 kaldirildi)
    $stmt = $con->prepare("SELECT DISTINCT kategori_id FROM urunler WHERE kategori_id IN ($yer_tutucu)");
    $stmt->execute($idler);
    $dolu_kategoriler = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $silinebilecek = array_values(array_diff($idler, $dolu_kategoriler));

    foreach ($dolu_kategoriler as $id) {
        $hatalar[] = "ID: $id kategorisine ait ürünler var";
    }

    $silinen = [];
    if ($silinebilecek) {
        $silinen_yer_tutucu = implode(',', array_fill(0, count($silinebilecek), '?'));

        // Tek islem: yarim silme (bazisi gitti, bazisi kaldi) durumu olmaz
        $con->beginTransaction();
        try {
            $stmt = $con->prepare("DELETE FROM kategoriler WHERE id IN ($silinen_yer_tutucu)");
            $stmt->execute($silinebilecek);
            $silinen = $silinebilecek;
            $con->commit();
        } catch (PDOException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    $silinen_sayisi = count($silinen);
    
    if ($silinen_sayisi > 0) {
        $mesaj = "$silinen_sayisi kategori silindi.";
        if ($hatalar) {
            $mesaj .= " Bazı kategoriler silinemedi: " . implode(', ', $hatalar);
        }
        echo json_encode(['success' => true, 'message' => $mesaj]);
    } else {
        echo json_encode(['success' => false, 'message' => $hatalar ? 'Hiçbir kategori silinemedi: ' . implode(', ', $hatalar) : 'Hiçbir kategori silinemedi.']);
    }
    
} catch (Exception $e) {
    error_log('kategori coklusil: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Islem sirasinda sunucu hatasi olustu.']);
}

