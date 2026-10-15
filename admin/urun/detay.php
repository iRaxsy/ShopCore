<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik cikti baslamadan ONCE calisir
$id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    admin_hata('HATA: Kayıt bulunamadı.');
}

try {
    $sorgu = "SELECT u.id, u.urunadi, u.aciklama, u.fiyat, u.resim, 
                     u.olusturma_tarihi, u.guncelleme_tarihi, k.kategoriadi 
              FROM urunler u 
              LEFT JOIN kategoriler k ON u.kategori_id = k.id 
              WHERE u.id = ? 
              LIMIT 1";
    
    $stmt = $con->prepare($sorgu);
    $stmt->execute([$id]);
    $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$kayit) {
        admin_hata('HATA: Kayıt bulunamadı.', '', 404);
    }
    
    // extract($kayit) yerine açık değişken atamaları
    $id                = (int)$kayit['id'];
    $urunadi           = $kayit['urunadi'];
    $aciklama          = $kayit['aciklama'];
    $fiyat             = $kayit['fiyat'];
    $resim             = $kayit['resim'];
    $olusturma_tarihi  = $kayit['olusturma_tarihi'];
    $guncelleme_tarihi = $kayit['guncelleme_tarihi'];
    $kategoriadi       = $kayit['kategoriadi'];
    // Gorsel adi ciktilamada esc() ile kacirilir; burada ham deger tutulur.
    
    function tarihiFormatla($tarih) {
        if (!$tarih || $tarih == '0000-00-00 00:00:00') return "Belirtilmemiş";
        
        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
                 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        
        $tarihObj = new DateTime($tarih);
        return $tarihObj->format('d') . ' ' . $aylar[$tarihObj->format('n')-1] . ' ' . 
               $tarihObj->format('Y') . ' - ' . $tarihObj->format('H:i:s');
    }
    
    $ek_resim_stmt = $con->prepare("SELECT * FROM urun_resimleri WHERE urun_id = ? ORDER BY sira");
    $ek_resim_stmt->execute([$id]);
    $ek_resimler = $ek_resim_stmt->fetchAll(PDO::FETCH_ASSOC);
    $ek_resim_sayisi = count($ek_resimler);
    
} catch (PDOException $e) {
    admin_hata('HATA: Ürün bilgileri yüklenemedi.', $e->getMessage(), 500);
}

include "../header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Ürün Bilgisi</h1>
    </div>
    
    <div class="product-detail-container">
        <!-- Sol Taraf - Resim Galerisi -->
        <div class="product-images">
            <div class="image-gallery-card">
                <h2 class="gallery-title">Ürün Görselleri</h2>
                
                <!-- Ana Resim -->
                <div class="main-image-container">
                    <?php if ($resim): ?>
                        <img id="mainImage" src="../../content/images/<?= esc($resim) ?>" 
                             alt="<?= esc($urunadi) ?>" 
                             <?= resim_boyut($resim) ?> loading="lazy" decoding="async"
                             class="main-image">
                    <?php else: ?>
                        <div class="text-soluk fst-italic" style="text-align: center; padding: 40px;">
                            <svg class="ik" style="font-size: 3em; display: block; margin-bottom: 10px;" aria-hidden="true"><use href="#lk-image"></use></svg>
                            Ana ürün görseli yok
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Ek Resimler -->
                <?php if ($ek_resim_sayisi > 0): ?>
                    <h3 class="text-lacivert" style="font-size: 1.2rem; margin-top: 25px; margin-bottom: 15px;">
                        Ek Resimler (<?= $ek_resim_sayisi ?>)
                    </h3>
                    <div class="thumbnail-gallery" data-gorsel-tabani="../../content/images/">
                        <?php if ($resim): ?>
                            <div class="thumbnail-item active" data-resim="<?= esc($resim) ?>">
                                <img src="../../content/images/<?= esc($resim) ?>" 
                                     alt="Ana resim" 
                                     <?= resim_boyut($resim) ?> loading="lazy" decoding="async"
                                     class="thumbnail-img">
                            </div>
                        <?php endif; ?>
                        
                        <?php foreach ($ek_resimler as $index => $ek_resim): ?>
                            <div class="thumbnail-item" data-resim="<?= esc($ek_resim['resim_yolu']) ?>">
                                <img src="../../content/images/<?= esc($ek_resim['resim_yolu']) ?>" 
                                     alt="Ek resim <?= $index + 1 ?>" 
                                     <?= resim_boyut($ek_resim['resim_yolu']) ?> loading="lazy" decoding="async"
                                     class="thumbnail-img">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-top: 20px; text-align: center;">
                        <svg class="ik" aria-hidden="true"><use href="#lk-info"></use></svg> Bu ürüne ait ek resim bulunmamaktadır.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sağ Taraf - Ürün Bilgileri -->
        <div class="product-info">
            <div class="info-card">
                <h2 class="product-title"><?= esc($urunadi) ?></h2>
                
                <!-- AÇIKLAMA -->
                <div style="margin-bottom: 20px;">
                    <strong class="text-lacivert" style="display: block; margin-bottom: 10px; font-size: 1.1rem;">Açıklama</strong>
                    <div class="description-content">
                        <?php if (empty(trim(strip_tags($aciklama)))): ?>
                            <div class="text-soluk fst-italic">
                                Bu ürün için açıklama girilmemiş.
                            </div>
                        <?php else: ?>
                            <?= temiz_html($aciklama) ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Detay Bilgileri TABLOSU -->
                <table class="info-table">
                    <!-- FİYAT SATIRI -->
                    <tr>
                        <td>Fiyat</td>
                        <td>
                            <span class="price-badge">
                                <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                                <?= para($fiyat) ?>
                            </span>
                        </td>
                    </tr>
                    
                    <!-- Kategori Satırı -->
                    <tr>
                        <td>Kategori</td>
                        <td>
                            <?php if ($kategoriadi): ?>
                                <span class="category-badge">
                                    <svg class="ik" aria-hidden="true"><use href="#lk-tag"></use></svg>
                                    <?= esc($kategoriadi) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-soluk fst-italic">
                                    Kategori belirtilmemiş
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Tarih Satırları -->
                    <tr>
                        <td>Ürün Giriş Tarihi</td>
                        <td><span class="date-info"><?= tarihiFormatla($olusturma_tarihi) ?></span></td>
                    </tr>
                    <tr>
                        <td>Son Güncelleme Tarihi</td>
                        <td><span class="date-info"><?= tarihiFormatla($guncelleme_tarihi) ?></span></td>
                    </tr>
                </table>
                
                <!-- İşlem Butonları -->
                <div class="button-container">
                    <a href='liste.php' class='btn btn-danger'>
                        <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg>
                        Ürün Listesi
                    </a>
                    <a href='duzelt.php?id=<?= $id ?>' class='btn btn-primary'>
                        <svg class='ik' aria-hidden='true'><use href='#lk-pen'></use></svg>
                        Ürünü Düzenle
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gorsel galerisi davranisi harici dosyada (inline JS/onclick yok) -->
<?= varlik_js('content/js/admin-urun-detay.js') ?>

<?php include "../footer.php"; ?>
