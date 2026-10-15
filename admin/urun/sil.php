<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

app_session_start();
admin_zorunlu();
csrf_dogrula();

$con = db();
header('Content-Type: application/json; charset=utf-8');

// Degisiklik yapan uc yalniz POST kabul eder (GET loglara/gecmise sizabilir)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'HATA: Bu islem yalniz POST ile calistirilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sil_tipi = $_POST['sil_tipi'] ?? 'urun';

try {
    if ($sil_tipi === 'urun') {
        $id = (isset($_POST['id']) && is_numeric($_POST['id'])) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'HATA: Id bilgisi bulunamadı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Urun basta dogrulanir: yoksa hicbir dosyaya dokunulmaz
        $stmt = $con->prepare("SELECT id, resim FROM urunler WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $kayit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$kayit) {
            echo json_encode(['status' => 'error', 'message' => 'Ürün bulunamadı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $con->prepare("SELECT resim_yolu FROM urun_resimleri WHERE urun_id = ?");
        $stmt->execute([$id]);
        $ek_resimler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Veri silmeleri tek islemde; dosya silme commit sonrasina birakilir
        // (rollback olursa diskteki dosyalar yerinde kalir, yetim kayit olmaz)
        $con->beginTransaction();
        try {
            $stmt = $con->prepare("DELETE FROM urun_resimleri WHERE urun_id = ?");
            $stmt->execute([$id]);

            $stmt = $con->prepare("DELETE FROM urunler WHERE id = ?");
            $stmt->execute([$id]);

            $con->commit();
        } catch (PDOException $e) {
            $con->rollBack();
            throw $e;
        }

        $silinen_ek_resim_sayisi = 0;
        foreach ($ek_resimler as $ek_resim) {
            if ($ek_resim['resim_yolu']) {
                // resim_dosyalari(): orijinal + kart varyantlari birlikte kalkar
                $dosyalar = resim_dosyalari($ek_resim['resim_yolu']);
                foreach ($dosyalar as $silinecek_ek_resim) {
                    @unlink($silinecek_ek_resim);
                }
                if ($dosyalar) {
                    $silinen_ek_resim_sayisi++;
                }
            }
        }

        $resim = $kayit['resim'];
        if ($resim && $resim !== APP_DEFAULT_GORSEL) {
            foreach (resim_dosyalari($resim) as $silinecek_resim) {
                @unlink($silinecek_resim);
            }
        }

        $mesaj = 'Ürün başarıyla silindi.';
        if ($silinen_ek_resim_sayisi > 0) {
            $mesaj .= " $silinen_ek_resim_sayisi adet ek resim de silindi.";
        }

        echo json_encode([
            'status' => 'success',
            'message' => $mesaj
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    elseif ($sil_tipi === 'ek_resim') {
        $resim_id = (isset($_POST['resim_id']) && is_numeric($_POST['resim_id'])) ? (int)$_POST['resim_id'] : 0;
        $urun_id  = (isset($_POST['urun_id']) && is_numeric($_POST['urun_id'])) ? (int)$_POST['urun_id'] : 0;

        if ($resim_id <= 0 || $urun_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'HATA: Resim veya ürün id bilgisi bulunamadı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $con->prepare("SELECT resim_yolu FROM urun_resimleri WHERE id = ? AND urun_id = ?");
        $stmt->execute([$resim_id, $urun_id]);
        $resim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resim) {
            echo json_encode(['status' => 'error', 'message' => 'Resim bulunamadı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $con->prepare("DELETE FROM urun_resimleri WHERE id = ? AND urun_id = ?");
        if ($stmt->execute([$resim_id, $urun_id])) {
            $resim_yolu = $resim['resim_yolu'];
            if ($resim_yolu) {
                foreach (resim_dosyalari($resim_yolu) as $silinecek_resim) {
                    @unlink($silinecek_resim);
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Ek resim başarıyla silindi.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Resim veritabanından silinemedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

} catch (PDOException $e) {
    error_log('urun sil DB: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Islem sirasinda sunucu hatasi olustu.'], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    error_log('urun sil: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Islem sirasinda sunucu hatasi olustu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Bilinmeyen istek'], JSON_UNESCAPED_UNICODE);
exit;
