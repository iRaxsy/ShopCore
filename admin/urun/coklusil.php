<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

app_session_start();
admin_zorunlu();
csrf_dogrula();

$con = db();
header('Content-Type: application/json; charset=utf-8');

// Degisiklik yapan uc yalniz POST kabul eder (GET loglara/gecmise sizabilir).
// Id listesi ya form alani (`id[]`) ya da JSON govde ({id:[...]}) ile gelir.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Bu islem yalniz POST ile calistirilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $ids = [];

    if (isset($_POST['id'])) {
        $ids = (array)$_POST['id'];
    } elseif ($govde = file_get_contents('php://input')) {
        $veri = json_decode($govde, true);
        if (isset($veri['id'])) {
            $ids = is_array($veri['id']) ? $veri['id'] : [$veri['id']];
        }
    }
    
    if (empty($ids)) {
        throw new Exception('Silinecek kayıt bulunamadı.');
    }
    
    $ids = array_filter(array_map('intval', $ids), function($id) {
        return $id > 0;
    });
    
    if (empty($ids)) {
        throw new Exception('Geçerli ID bulunamadı.');
    }
    
    $ids = array_values($ids);
    $yer_tutucu = implode(',', array_fill(0, count($ids), '?'));

    // Tek-erisim (single round-trip): varlik ve resim listeleri dongu disinda alinir
    $stmt = $con->prepare("SELECT id, resim FROM urunler WHERE id IN ($yer_tutucu)");
    $stmt->execute($ids);
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $toplamSilinenUrun = count($urunler);
    $toplamSilinenAnaResim = $toplamSilinenEkResim = 0;

    if ($toplamSilinenUrun === 0) {
        throw new Exception('Silinecek kayıt bulunamadı.');
    }

    $bulunan_ids = array_values($urunler ? array_column($urunler, 'id') : []);
    $bulunan_yer_tutucu = implode(',', array_fill(0, count($bulunan_ids), '?'));

    $stmt = $con->prepare("SELECT urun_id, resim_yolu FROM urun_resimleri WHERE urun_id IN ($bulunan_yer_tutucu)");
    $stmt->execute($bulunan_ids);
    $ek_resimler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Veri silmeleri tek islem; dosyalar yalnizca commit sonra silinir
    $con->beginTransaction();
    try {
        $stmt = $con->prepare("DELETE FROM urun_resimleri WHERE urun_id IN ($bulunan_yer_tutucu)");
        $stmt->execute($bulunan_ids);

        $stmt = $con->prepare("DELETE FROM urunler WHERE id IN ($bulunan_yer_tutucu)");
        $stmt->execute($bulunan_ids);

        $con->commit();
    } catch (PDOException $e) {
        $con->rollBack();
        throw $e;
    }

    foreach ($ek_resimler as $ek_resim) {
        if (!empty($ek_resim['resim_yolu'])) {
            // resim_dosyalari(): orijinal + kart varyantlari birlikte kalkar
            $dosyalar = resim_dosyalari($ek_resim['resim_yolu']);
            foreach ($dosyalar as $silinecek_ek_resim) {
                @unlink($silinecek_ek_resim);
            }
            if ($dosyalar) {
                $toplamSilinenEkResim++;
            }
        }
    }

    foreach ($urunler as $urun) {
        $resim = $urun['resim'];
        if ($resim && $resim !== APP_DEFAULT_GORSEL) {
            $dosyalar = resim_dosyalari($resim);
            foreach ($dosyalar as $silinecek_resim) {
                @unlink($silinecek_resim);
            }
            if ($dosyalar) {
                $toplamSilinenAnaResim++;
            }
        }
    }

    $mesaj = "{$toplamSilinenUrun} ürün başarıyla silindi.";
    if ($toplamSilinenAnaResim > 0) $mesaj .= " {$toplamSilinenAnaResim} adet ana resim silindi.";
    if ($toplamSilinenEkResim > 0) $mesaj .= " {$toplamSilinenEkResim} adet ek resim silindi.";
    
    echo json_encode([
        'success' => true,
        'message' => $mesaj,
        'silinen_urun_sayisi' => $toplamSilinenUrun,
        'silinen_ana_resim_sayisi' => $toplamSilinenAnaResim,
        'silinen_ek_resim_sayisi' => $toplamSilinenEkResim
    ]);
    
} catch (PDOException $e) {
    error_log("urun coklusil DB hatasi: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Islem sirasinda sunucu hatasi olustu.']);
} catch (Exception $e) {
    error_log("urun coklusil hata: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Islem sirasinda gecersiz istek.']);
}

