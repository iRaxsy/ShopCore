<?php
/**
 * Kullanici siparis gecmisi. Yalnizca oturumdaki kullanicinin kendi siparisleri
 * listelenir (WHERE kullanici_id = ?); id her zaman session'dan alinir, POST/GET
 * üzerinden baska bir hesabın siparisi getirilemez.
 *
 * Eski misafir siparisleri (kullanici_id NULL) burada GORUNMEZ - hesapla
 * eslestirilemedikleri icin bilincli olarak disarida birakildilar.
 */
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();
kullanici_zorunlu();

$con = db();
$id  = kullanici_id();

$siparisler = [];
$kalemler   = [];

try {
    // En yeni 50 siparis gosterilir; hesap gecmisi buysuye sayfalama eklenir.
    $stmt = $con->prepare(
        'SELECT id, numarasi, olusturma_tarihi, durum, odeme_tipi,
                ara_toplam, kargo, toplam, il, ilce, adres
         FROM siparisler
         WHERE kullanici_id = ?
         ORDER BY olusturma_tarihi DESC, id DESC
         LIMIT 50'
    );
    $stmt->execute([$id]);
    $siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kalemler TEK sorguda cekilir (satir basina sorgu/N+1 yok), PHP tarafinda
    // siparis id'sine gore gruplanir.
    if ($siparisler) {
        $idler      = array_column($siparisler, 'id');
        $yer_tutucu = implode(',', array_fill(0, count($idler), '?'));
        $kalemStmt  = $con->prepare(
            "SELECT siparis_id, urun_adi, birim_fiyat, adet, satir_toplam
             FROM siparis_kalemleri
             WHERE siparis_id IN ($yer_tutucu)
             ORDER BY siparis_id, id"
        );
        $kalemStmt->execute($idler);
        foreach ($kalemStmt->fetchAll(PDO::FETCH_ASSOC) as $kalem) {
            $kalemler[(int)$kalem['siparis_id']][] = $kalem;
        }
    }
} catch (PDOException $e) {
    error_log('siparislerim: ' . $e->getMessage());
    flash('hata', 'Sipariş geçmişiniz şu anda yüklenemedi. Lütfen tekrar deneyin.');
}

include 'header.php';
?>
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="baslik mb-0">
            <svg class="ik me-2" aria-hidden="true"><use href="#lk-receipt"></use></svg>Siparişlerim
        </h1>
        <a href="hesabim.php" class="btn btn-outline-primary">
            <svg class="ik me-2" aria-hidden="true"><use href="#lk-circle-user"></use></svg>Hesabım
        </a>
    </div>

    <?php if (!$siparisler): ?>
        <div class="alert alert-warning text-center py-5">
            <svg class="ik mb-3" style="font-size: 3rem;" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg>
            <h3 class="h4">Henüz siparişiniz yok</h3>
            <p class="mb-4">Tamamladığınız siparişler bu listede görünecek.</p>
            <a href="urunler.php" class="btn btn-primary">
                <svg class="ik me-2" aria-hidden="true"><use href="#lk-shopping-bag"></use></svg>Alışverişe Başla
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($siparisler as $siparis): ?>
            <?php
            $sid    = (int)$siparis['id'];
            $durum  = siparis_durum_gorunum($siparis['durum']);
            $kalemlerBu = $kalemler[$sid] ?? [];
            ?>
            <div class="card shadow-sm mb-3 siparis-kart">
                <div class="card-header bg-body siparis-baslik">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-bold"><?= esc($siparis['numarasi']) ?></span>
                            <span class="badge bg-<?= esc($durum['sinif']) ?> ms-2"><?= esc($durum['etiket']) ?></span>
                            <div class="text-muted small mt-1">
                                <?= esc(date('d.m.Y H:i', strtotime((string)$siparis['olusturma_tarihi']))) ?>
                                &middot; <?= esc(siparis_odeme_etiketi($siparis['odeme_tipi'])) ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fs-5 fw-bold text-success"><?= para((float)$siparis['toplam']) ?>&#8378;</div>
                            <?php if ($kalemlerBu): ?>
                                <button class="btn btn-outline-secondary btn-sm mt-1" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#siparis-<?= $sid ?>"
                                        aria-expanded="false" aria-controls="siparis-<?= $sid ?>">
                                    <svg class="ik me-1" aria-hidden="true"><use href="#lk-list"></use></svg>Ürünleri göster
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($kalemlerBu): ?>
                    <div class="collapse" id="siparis-<?= $sid ?>">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0 kalem-liste">
                                    <thead>
                                        <tr>
                                            <th>Ürün</th>
                                            <th class="text-center">Adet</th>
                                            <th class="text-end">Birim</th>
                                            <th class="text-end">Toplam</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($kalemlerBu as $kalem): ?>
                                            <tr>
                                                <td><?= esc($kalem['urun_adi']) ?></td>
                                                <td class="text-center"><?= (int)$kalem['adet'] ?></td>
                                                <td class="text-end"><?= para((float)$kalem['birim_fiyat']) ?>&#8378;</td>
                                                <td class="text-end"><?= para((float)$kalem['satir_toplam']) ?>&#8378;</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-top">
                                            <td colspan="3" class="text-end">Ara Toplam</td>
                                            <td class="text-end"><?= para((float)$siparis['ara_toplam']) ?>&#8378;</td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">Kargo</td>
                                            <td class="text-end">
                                                <?php if ((float)$siparis['kargo'] === 0.0): ?>
                                                    <span class="text-success">Ücretsiz</span>
                                                <?php else: ?>
                                                    <?= para((float)$siparis['kargo']) ?>&#8378;
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">Toplam</td>
                                            <td class="text-end fw-bold text-success"><?= para((float)$siparis['toplam']) ?>&#8378;</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p class="text-muted small mb-0 mt-3">
                                <svg class="ik me-1" aria-hidden="true"><use href="#lk-truck"></use></svg>
                                Teslimat: <?= esc($siparis['il']) ?>/<?= esc($siparis['ilce']) ?>, <?= esc($siparis['adres']) ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
