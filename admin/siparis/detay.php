<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

$id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    admin_hata('HATA: Id bilgisi bulunamadı.');
}

// ---- Kaydi oku (mantik, cikti baslamadan once) ----
// Detay sayfasi SALT OKUNUR. Siparis durum degisimi bilincli olarak yalniz
// admin/siparis/liste.php'deki hizli durum formundan yapilir; burada ne POST
// handler'i ne de mutasyon var. Admin Notlari arayuzu 005 migration'i ile
// (siparisler.notlar kolonu) tamamen kaldirildi.
try {
    $stmt = $con->prepare("SELECT * FROM siparisler WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $siparis = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$siparis) {
        admin_hata('HATA: Sipariş bulunamadı.', '', 404);
    }

    // Kalemler tek sorguda; urun silinmisse bile urun_adi kopyasi okunabilir kalir
    $kalemStmt = $con->prepare(
        "SELECT k.*, u.resim, u.stok
         FROM siparis_kalemleri k
         LEFT JOIN urunler u ON u.id = k.urun_id
         WHERE k.siparis_id = ?
         ORDER BY k.id"
    );
    $kalemStmt->execute([$id]);
    $kalemler = $kalemStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    admin_hata('HATA: Sipariş bilgileri yüklenemedi.', $e->getMessage(), 500);
}

$durum_gorunum = siparis_durum_gorunum($siparis['durum']);
$odeme_etiketi = siparis_odeme_etiketi($siparis['odeme_tipi']);

include "../header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Sipariş Detayı</h1>
    </div>

    <div class="siparis-ozet-kart">
        <div class="siparis-baslik-satiri">
            <div>
                <span class="siparis-numara"><?= esc($siparis['numarasi']) ?></span>
                <span class="badge bg-<?= esc($durum_gorunum['sinif']) ?>"><?= esc($durum_gorunum['etiket']) ?></span>
            </div>
            <div class="text-soluk">
                <svg class="ik" aria-hidden="true"><use href="#lk-list"></use></svg>
                <?= esc(date('d.m.Y H:i', strtotime($siparis['olusturma_tarihi']))) ?>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td>Müşteri</td>
                <td>
                    <strong><?= esc($siparis['adsoyad']) ?></strong><br>
                    <?php if (site_bilgisi('eposta') !== '' || $siparis['eposta'] !== ''): ?>
                        <a href="mailto:<?= esc($siparis['eposta']) ?>" class="text-lacivert"><?= esc($siparis['eposta']) ?></a><br>
                    <?php endif; ?>
                    <span class="date-info"><?= esc($siparis['telefon']) ?></span>
                </td>
            </tr>
            <tr>
                <td>Teslimat Adresi</td>
                <td>
                    <?= esc($siparis['adres']) ?><br>
                    <?= esc($siparis['il']) ?> / <?= esc($siparis['ilce']) ?>
                    <?= $siparis['posta_kodu'] ? ' - ' . esc($siparis['posta_kodu']) : '' ?>
                    <?php if ($siparis['kurum']): ?>
                        <br><span class="text-soluk">Kurum: <?= esc($siparis['kurum']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Ödeme Yöntemi</td>
                <td><span class="category-badge"><?= esc($odeme_etiketi) ?></span></td>
            </tr>
            <tr>
                <td>Tutarlar</td>
                <td>
                    Ara toplam: <strong><?= para($siparis['ara_toplam']) ?>&#8378;</strong> &nbsp;|&nbsp;
                    Kargo:
                    <?php if ((float)$siparis['kargo'] === 0.0): ?>
                        <span class="text-soluk">Ücretsiz</span>
                    <?php else: ?>
                        <strong><?= para($siparis['kargo']) ?>&#8378;</strong>
                    <?php endif; ?>
                    &nbsp;|&nbsp;
                    <span class="price-badge">
                        <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                        <?= para($siparis['toplam']) ?>&#8378;
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="text-lacivert siparis-bolum-basligi">
        <svg class="ik" aria-hidden="true"><use href="#lk-receipt"></use></svg>
        Sipariş Kalemleri (<?= count($kalemler) ?>)
    </h2>

    <div class="table-responsive">
        <table class="table table-hover table-bordered">
            <thead>
                <tr>
                    <th>Ürün</th>
                    <th class="text-center">Birim Fiyat</th>
                    <th class="text-center">Adet</th>
                    <th class="text-center">Toplam</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kalemler as $kalem): ?>
                <tr>
                    <td>
                        <?php if ($kalem['resim']): ?>
                            <img src="../../content/images/<?= esc($kalem['resim']) ?>" alt=""
                                 <?= resim_boyut($kalem['resim']) ?> loading="lazy" decoding="async"
                                 class="img-thumbnail" style="width: 48px; height: 48px; object-fit: cover;">
                        <?php endif; ?>
                        <strong><?= esc($kalem['urun_adi']) ?></strong>
                    </td>
                    <td class="text-center"><?= para($kalem['birim_fiyat']) ?>&#8378;</td>
                    <td class="text-center"><?= (int)$kalem['adet'] ?></td>
                    <td class="text-center"><strong><?= para($kalem['satir_toplam']) ?>&#8378;</strong></td>
                    <td>
                        <?php if ($kalem['urun_id'] === null): ?>
                            <span class="badge bg-secondary">Ürün silinmiş</span>
                        <?php elseif ((int)$kalem['stok'] < (int)$kalem['adet']): ?>
                            <span class="badge bg-warning text-dark">Stok uyarısı: <?= (int)$kalem['stok'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Stokta yeterli</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../footer.php"; ?>
