<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik (sorgular) cikti baslamadan ONCE calisir
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    admin_hata('HATA: Geçersiz kategori ID!');
}

$kategori_id = (int)$_GET['id'];

$sayfa = isset($_GET['sayfa']) && is_numeric($_GET['sayfa']) ? max(1, (int)$_GET['sayfa']) : 1;
$sayfa_kayit_sayisi = 5;
$sayfa_baslangic = ($sayfa - 1) * $sayfa_kayit_sayisi;

try {
    $kategori_stmt = $con->prepare("SELECT kategoriadi FROM kategoriler WHERE id = ?");
    $kategori_stmt->execute([$kategori_id]);
    $kategori = $kategori_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$kategori) {
        admin_hata('HATA: Kategori bulunamadı!', '', 404);
    }
    
    // Kategori adi ham tutulur; ciktilamada esc() ile kacirilir
    $kategori_adi = $kategori['kategoriadi'];
    
    $toplam_stmt = $con->prepare("SELECT COUNT(*) as toplam FROM urunler WHERE kategori_id = ?");
    $toplam_stmt->execute([$kategori_id]);
    $kayit_sayisi = (int)$toplam_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];
    $sayfa_sayisi = (int)ceil($kayit_sayisi / $sayfa_kayit_sayisi);
    
    $stmt = $con->prepare("SELECT id, urunadi, aciklama, fiyat FROM urunler WHERE kategori_id = ? ORDER BY id DESC LIMIT ?, ?");
    $stmt->bindParam(1, $kategori_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $sayfa_baslangic, PDO::PARAM_INT);
    $stmt->bindParam(3, $sayfa_kayit_sayisi, PDO::PARAM_INT);
    $stmt->execute();
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    admin_hata('Veritabanı hatası: İşlem sırasında bir sorun oluştu.', $e->getMessage(), 500);
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Ürün Listesi</h1>
    </div>
    
    <!-- Kategori Bilgisi -->
    <div class="back-button-container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <a href='liste.php' class='btn btn-danger'>
                <svg class='ik' aria-hidden='true'><use href='#lk-arrow-left'></use></svg> Kategori Listesine Dön
            </a>
            
            <div class="category-info">
                <svg class="ik text-lacivert" aria-hidden="true"><use href="#lk-tag"></use></svg>
                Kategori: <span class="text-danger" style="font-weight: 700;"><?= esc($kategori_adi) ?></span>
            </div>
        </div>
    </div>
    
    <?php // Urunler sayfa baslangicinda sorgulandi ($urunler); burada yalnizca cikti uretilir ?>
    <?php if ($urunler): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ürün Adı</th>
                        <th>Açıklama</th>
                        <th>Fiyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($urunler as $kayit): ?>
                    <tr>
                        <td><strong><?= (int)$kayit['id'] ?></strong></td>
                        <td><strong><?= esc($kayit['urunadi']) ?></strong></td>
                        <td class="aciklama-hucre"><?= esc(ozetle($kayit['aciklama'], 120)) ?></td>
                        <td><span class="fiyat"><?= para($kayit['fiyat']) ?> &#8378;</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php /* Kopya sayfalama blokunun yerini merkezi yardimci aliyor */ ?>
        <?= admin_sayfalama($sayfa, $sayfa_sayisi, 'urunler.php', ['id' => $kategori_id]) ?>

        <div class="info-text">
            <svg class="ik text-lacivert" style="margin-right: 8px;" aria-hidden="true"><use href="#lk-info"></use></svg>
            Toplam <strong><?= (int)$kayit_sayisi ?></strong> ürün bulundu.
            <?php if ($sayfa_sayisi > 1): ?>
                Sayfa <strong><?= (int)$sayfa ?></strong>/<strong><?= (int)$sayfa_sayisi ?></strong>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <svg class="ik" style="font-size: 1.5em; margin-right: 10px;" aria-hidden="true"><use href="#lk-info"></use></svg>
            <strong>Bu kategoriye ait ürün bulunamadı.</strong>
        </div>
    <?php endif; ?>
</div>

<?php include "../footer.php"; ?>
