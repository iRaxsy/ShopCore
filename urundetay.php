<?php
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();
$con = db();

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect('urunler.php');
}

try {
    $sorgu = "SELECT urunler.*, kategoriler.kategoriadi FROM urunler 
              LEFT JOIN kategoriler ON urunler.kategori_id = kategoriler.id 
              WHERE urunler.id = ?";
    $stmt = $con->prepare($sorgu);
    $stmt->bindParam(1, $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        redirect('urunler.php');
    }
    
    $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
    // extract() yerine acik atama (degisken ezme riskini kaldırır)
    $urunadi      = $kayit['urunadi'];
    $fiyat        = $kayit['fiyat'];
    $aciklama     = $kayit['aciklama'];
    $resim        = $kayit['resim'];
    $giris_tarihi = $kayit['giris_tarihi'];
    $dzltm_tarihi = $kayit['dzltm_tarihi'];
    $kategori_id  = $kayit['kategori_id'];
    $kategoriadi  = $kayit['kategoriadi'];
    
    $resimSorgu = "SELECT * FROM urun_resimleri 
                   WHERE urun_id = ? 
                   ORDER BY sira ASC, eklenme_tarihi DESC";
    $resimStmt = $con->prepare($resimSorgu);
    $resimStmt->bindParam(1, $id, PDO::PARAM_INT);
    $resimStmt->execute();
    $ekResimler = $resimStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $exception) {
    error_log('urundetay: ' . $exception->getMessage());
    redirect('urunler.php');
}

// Mantik tamamlandi; HTML ciktiisi ancak simdi baslar
// (redirect() header() kullandigi icin cikti oncesinde calismali)
include "header.php";
?>

<div class="container mt-4 mb-4">
    <div class="row">
        <div class="col-md-6">
            <!-- Ana Resim ve Ek Resimler Galerisi -->
            <div class="row">
                <!-- Ana Resim -->
                <div class="col-12 mb-4">
                    <?php 
                    $kullanilacakResim = resim_yolu($resim);
                    // Ana gorsel sayfanin LCP adayi: lazy degil, fetchpriority yuksek.
                    $anaResimBoyut = resim_boyut($resim);
                    
                    echo "<img src='" . esc($kullanilacakResim) . "' alt='" . esc($urunadi) . "' 
                          $anaResimBoyut loading='eager' fetchpriority='high' decoding='async'
                          class='img-fluid rounded shadow-lg' id='anaResim' 
                          style='cursor: pointer; max-height: 400px; object-fit: contain;' 
                          data-bs-toggle='modal' data-bs-target='#resimModal' 
                          data-bs-src='" . esc($kullanilacakResim) . "'>";
                    ?>
                </div>
                
                <!-- Ek Resimler Thumbnail -->
                <?php if (count($ekResimler) > 0): ?>
                <div class="col-12">
                    <h5 class="baslik mb-3">Diğer Görseller</h5>
                    <div class="row g-2">
                        <!-- Ana resim thumbnail -->
                        <div class="col-3">
                            <div class="border rounded p-1 ek-resim-thumbnail active" 
                                 data-resim="<?= esc($kullanilacakResim) ?>">
                                <img src="<?= esc(resim_yolu($resim, RESIM_KART_GENISLIK)) ?>" 
                                     <?= resim_boyut($resim, RESIM_KART_GENISLIK) ?> loading="lazy" decoding="async"
                                     alt="<?= esc($urunadi) ?> - Ana"
                                     class="img-fluid rounded"
                                     style="height: 80px; object-fit: cover; width: 100%;">
                            </div>
                        </div>
                        
                        <!-- Ek resimler thumbnails -->
                        <?php foreach ($ekResimler as $ekResim): 
                            $kullanilacakEkResim = resim_yolu($ekResim['resim_yolu']);
                        ?>
                        <div class="col-3">
                            <div class="border rounded p-1 ek-resim-thumbnail" 
                                 data-resim="<?= esc($kullanilacakEkResim) ?>">
                                <img src="<?= esc(resim_yolu($ekResim['resim_yolu'], RESIM_KART_GENISLIK)) ?>" 
                                     <?= resim_boyut($ekResim['resim_yolu'], RESIM_KART_GENISLIK) ?> loading="lazy" decoding="async"
                                     alt="<?= esc($urunadi) ?> - <?= esc($ekResim['id']) ?>"
                                     class="img-fluid rounded"
                                     style="height: 80px; object-fit: cover; width: 100%;">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-6">
            <h2 class="baslik"><?= esc($urunadi) ?></h2>
            <h5 class="text-success"><?= esc(para($fiyat)) ?>&#8378;</h5><hr>
            <!-- Aciklama CKEditor'dan gelen zengin icerik; temiz_html() ile guvenlestirilip HTML olarak basilir -->
            <div class="mb-5 urun-aciklama"><?= temiz_html($aciklama) ?></div>
            <h6 class="text-info">Ürünün stoklarımıza girişi: <?= esc(date("d-m-Y", strtotime($giris_tarihi))) ?></h6>
            <h6 class="text-warning">En son bilgi güncelleme: <?= esc(date("d-m-Y", strtotime($dzltm_tarihi))) ?></h6>
            <hr>
            
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="number" min="1" max="99" class="form-control" value="1" id="urun_<?= (int)$id ?>">
                        <button class="btn btn-success sepete_ekle" type="button" id="<?= (int)$id ?>">
                            <svg class="ik" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg> Sepete Ekle
                        </button>
                    </div>
                </div>
            </div>
            
            <hr>
            <p><a href="<?= url('urunler.php', ['id' => (int)$kategori_id]) ?>" class="btn btn-dark p-3"><?= esc($kategoriadi) ?> kategorisindeki diğer ürünler</a></p>
        </div>
    </div>
    
    <!-- Resim Büyütme Modal -->
    <div class="modal fade" id="resimModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo esc($urunadi); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalResim" src="" class="img-fluid" alt="" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    
    <h4 class="baslik mt-5">Diğer Ürünler</h4>
    <div class="row urun-izgara">
        <?php
        /* Rastgele oneri urunler: ORDER BY RAND() her satira bir deger atayip
         * tabloyu dosyada siralar (LIMIT icin de tum tablo taranir). Kimlik listesi
         * ucuza cekilir, karistirma PHP'de yapilir, ardindan yalnizca secilen kayitlar
         * birincil anahtar uzerinden okunur. Davranis ayni: bu urun disindan 4 rastgele.
         */
        $oneri_sorgu = $con->prepare('SELECT id FROM urunler WHERE id != ?');
        $oneri_sorgu->execute([$id]);
        $oneri_idler = array_map('intval', $oneri_sorgu->fetchAll(PDO::FETCH_COLUMN));

        $veri = [];
        if ($oneri_idler) {
            $secilen = array_rand($oneri_idler, min(4, count($oneri_idler)));
            $secilen = is_array($secilen) ? $secilen : [$secilen];
            $yer_tutucu = implode(',', array_fill(0, count($secilen), '?'));
            $stmt = $con->prepare("SELECT id, urunadi, aciklama, fiyat, resim FROM urunler WHERE id IN ($yer_tutucu)");
            $stmt->execute($secilen);
            $veri = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        foreach ($veri as $kayit) { 
            $kullanilacakResim = resim_yolu($kayit['resim'], RESIM_KART_GENISLIK);
            $kartBoyut         = resim_boyut($kayit['resim'], RESIM_KART_GENISLIK);
        ?>
        <div class="col-6 col-md-3 mb-3">
            <div class="card h-100">
                <a href="<?= url('urundetay.php', ['id' => (int)$kayit['id']]) ?>">
                    <img class="card-img-top urun-kart-gorsel" src="<?= esc($kullanilacakResim) ?>" <?= $kartBoyut ?> loading="lazy" decoding="async" alt="<?= esc($kayit['urunadi']) ?>">
                </a>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?= esc($kayit['urunadi']) ?></h5>
                    <p class="card-text flex-grow-1"><?= esc(ozetle($kayit['aciklama'], 80)) ?></p>
                    <div class="mt-auto urun-kart-alt">
                        <button class="btn btn-outline-success btn-sm sepete_ekle float-start" id="<?= (int)$kayit['id'] ?>">
                            <svg class="ik" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg> Sepete Ekle
                        </button>
                        <span class="badge bg-success p-2 float-end"><?= esc(para($kayit['fiyat'])) ?>&#8378;</span>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<!-- JavaScript for Image Gallery -->
<?= varlik_js('content/js/urun-detay.js') ?>

<?php include "footer.php"; ?>
