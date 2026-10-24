<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Bildirimler flash() ile session'a birakilir; admin/header.php tek noktadan basar.

$id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    admin_hata('HATA: Id bilgisi bulunamadı.');
}

try {
    $stmt = $con->prepare("SELECT id, urunadi, aciklama, fiyat, stok, kategori_id, resim FROM urunler WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$kayit) {
        admin_hata('HATA: Kayıt bulunamadı.', '', 404);
    }
    
    // extract($kayit) yerine açık değişken atamaları (değişken ezme riskini kaldırır)
    $id          = (int)$kayit['id'];
    $urunadi     = $kayit['urunadi'];
    $aciklama    = $kayit['aciklama'];
    $fiyat       = $kayit['fiyat'];
    $stok        = (int)$kayit['stok'];
    $kategori_id = $kayit['kategori_id'];
    $resim       = $kayit['resim'];
    $mevcutResim = $resim ?? '';
    
    $ekResimlerStmt = $con->prepare("SELECT * FROM urun_resimleri WHERE urun_id = ? ORDER BY eklenme_tarihi DESC");
    $ekResimlerStmt->execute([$id]);
    $ekResimler = $ekResimlerStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $mevcutEkResimSayisi = count($ekResimler);
    $kalanEkResimHakki = 5 - $mevcutEkResimSayisi;
    
    // Formun kategori secenekleri
    $kategoriler = $con->query("SELECT id, kategoriadi FROM kategoriler ORDER BY kategoriadi")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    admin_hata(db_hata_mesaji($e, 'HATA: Ürün bilgileri yüklenemedi.'), $e->getMessage(), 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_dogrula();
        
        // 1) Girdi dogrulamasi - DB'ye ham deger yazilir, ciktilar esc() ile kacirilir
        $hatalar      = [];
        $bilgiNotlari = [];
        $urunadi      = temiz_metin($_POST['urunadi'] ?? '');
        $aciklama     = temiz_html($_POST['aciklama'] ?? '');
        $fiyatGirdi   = trim((string)($_POST['fiyat'] ?? ''));
        $fiyat        = filter_var($fiyatGirdi, FILTER_VALIDATE_FLOAT);
        $stokGirdi    = trim((string)($_POST['stok'] ?? '0'));
        $stok         = filter_var($stokGirdi, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $kategori_id  = (int)($_POST['kategori_id'] ?? 0);
        
        if ($urunadi === '') {
            $hatalar[] = 'Ürün adı boş olamaz.';
        }
        if ($fiyat === false) {
            $hatalar[] = 'Fiyat sayısal bir değer olmalıdır.';
            $fiyat     = $fiyatGirdi; // hata ekraninda kullanici girdisi korunur
        } elseif ($fiyat < 0) {
            $hatalar[] = 'Fiyat sıfır veya pozitif bir sayı olmalıdır.';
        }
        if ($stok === false) {
            $hatalar[] = 'Stok negatif olmayan bir tam sayı olmalıdır.';
            $stok      = $stokGirdi; // hata ekraninda kullanici girdisi korunur
        }
        $kategoriKontrol = $con->prepare("SELECT id FROM kategoriler WHERE id = ? LIMIT 1");
        $kategoriKontrol->execute([$kategori_id]);
        if ($kategori_id <= 0 || !$kategoriKontrol->fetch()) {
            $hatalar[] = 'Lütfen geçerli bir kategori seçin.';
        }
        
        $hedefKlasor = APP_ROOT . '/content/images/';
        
        // 2) Dosyalar ONCE dogrulanip tasinir (resim_yukle: MIME kontrolu + rastgele ad,
        //    istemcinin verdigi dosya adi kullanilmaz). Eski ana resme dokunulmaz;
        //    boylece basarisiz yuklemede veritabaninda olmayan dosyaya isaret eden
        //    bir guncelleme yapilmaz.
        $yeniResimAdi    = null;
        $ekResimYollari  = [];
        $tasinanDosyalar = [];
        
        if (!empty($_FILES['resim']['name'])) {
            $yukleme = resim_yukle($_FILES['resim'], $hedefKlasor);
            if ($yukleme['ok']) {
                $yeniResimAdi    = $yukleme['ad'];
                // resim_dosyalari(): gd ile islenmisse kart varyanti da geri alinir
                $tasinanDosyalar = array_merge($tasinanDosyalar, resim_dosyalari($yeniResimAdi));
            } else {
                $hatalar[] = 'Ana resim: ' . $yukleme['hata'];
            }
        }
        
        if (!empty($_FILES['ek_resimler']['name'][0])) {
            if ($kalanEkResimHakki <= 0) {
                // Kota dolu: kayit guncellemesini engellemez, yalnizca bilgilendirilir
                $bilgiNotlari[] = 'Ek resim hakkınız dolmuş; yeni ek resimler alınmadı.';
            } else {
                foreach (array_keys($_FILES['ek_resimler']['name']) as $i) {
                    if (count($ekResimYollari) >= $kalanEkResimHakki) {
                        $bilgiNotlari[] = "Kalan ek resim hakkınız $kalanEkResimHakki adet; fazlası alınmadı.";
                        break;
                    }
                    $yukleme = resim_yukle([
                        'name'     => $_FILES['ek_resimler']['name'][$i],
                        'type'     => $_FILES['ek_resimler']['type'][$i],
                        'tmp_name' => $_FILES['ek_resimler']['tmp_name'][$i],
                        'error'    => $_FILES['ek_resimler']['error'][$i],
                        'size'     => $_FILES['ek_resimler']['size'][$i],
                    ], $hedefKlasor);
                    
                    if ($yukleme['ok']) {
                        $ekResimYollari[]  = $yukleme['ad'];
                        $tasinanDosyalar   = array_merge($tasinanDosyalar, resim_dosyalari($yukleme['ad']));
                    } else {
                        $hatalar[] = esc($_FILES['ek_resimler']['name'][$i]) . ' - ' . $yukleme['hata'];
                    }
                }
            }
        }
        
        // 3) Hata varsa HICbir alan guncellenmez ve tasinan dosyalar geri alinir
        if ($hatalar) {
            foreach ($tasinanDosyalar as $dosya) {
                if (is_file($dosya)) {
                    @unlink($dosya);
                }
            }
            // yukleme hatalari ve bilgi notlari tek uyarida toplanir
            flash('uyari', implode(' | ', $hatalar));
            if ($bilgiNotlari) {
                flash('bilgi', implode(' | ', $bilgiNotlari));
            }
        } else {
            try {
                $con->beginTransaction();
                
                // 4) Kayit guncellemesi + ek resimler tek islemde (yarim guncelleme olmaz)
                if ($yeniResimAdi !== null) {
                    $stmt = $con->prepare("UPDATE urunler SET urunadi=?, aciklama=?, fiyat=?, stok=?, resim=?, kategori_id=? WHERE id=?");
                    $stmt->execute([$urunadi, $aciklama, $fiyat, $stok, $yeniResimAdi, $kategori_id, $id]);
                } else {
                    $stmt = $con->prepare("UPDATE urunler SET urunadi=?, aciklama=?, fiyat=?, stok=?, kategori_id=? WHERE id=?");
                    $stmt->execute([$urunadi, $aciklama, $fiyat, $stok, $kategori_id, $id]);
                }
                
                $ekResimStmt = $con->prepare("INSERT INTO urun_resimleri (urun_id, resim_yolu) VALUES (?, ?)");
                foreach ($ekResimYollari as $resimAdi) {
                    $ekResimStmt->execute([$id, $resimAdi]);
                }
                
                $con->commit();
                
                // 5) DB guncellemesi BAŞARILI: eski ana resmi ancak şimdi sil
                if ($yeniResimAdi !== null && $mevcutResim !== '' && $mevcutResim !== $yeniResimAdi
                    && $mevcutResim !== APP_DEFAULT_GORSEL && is_file($hedefKlasor . $mevcutResim)) {
                    // resim_dosyalari(): eski gorsele ait kart varyantlari da kalksin
                    foreach (resim_dosyalari($mevcutResim) as $eskiDosya) {
                        @unlink($eskiDosya);
                    }
                    $mevcutResim = $yeniResimAdi;
                }
                
                $basariMesaj = 'Kayıt güncellendi.';
                if ($ekResimYollari) {
                    $basariMesaj = count($ekResimYollari) . ' adet ek resim yüklendi. ' . $basariMesaj;
                }
                flash('basari', $basariMesaj);
                if ($bilgiNotlari) {
                    flash('bilgi', implode(' | ', $bilgiNotlari));
                }
                
                $ekResimlerStmt->execute([$id]);
                $ekResimler = $ekResimlerStmt->fetchAll(PDO::FETCH_ASSOC);
                $mevcutEkResimSayisi = count($ekResimler);
                $kalanEkResimHakki = 5 - $mevcutEkResimSayisi;
                
            } catch (PDOException $e) {
                if ($con->inTransaction()) $con->rollBack();
                // Islem geri alindi: DB'ye islenmemis dosyalar sahipsiz kalmasin
                foreach ($tasinanDosyalar as $dosya) {
                    if (is_file($dosya)) {
                        @unlink($dosya);
                    }
                }
                error_log('urun/duzelt: ' . $e->getMessage());
                flash('hata', 'Ürün güncellenemedi. Lütfen tekrar deneyin.');
            }
        }
    }

    include "../header.php";
    ?>


<!-- CKEditor 5 (yerel: content/vendor/ckeditor/) -->
<?= ckeditor() ?>

<div class="container">
    <div class="page-header">
        <h1>Ürün Güncelleme</h1>
    </div>
    
    <div class="product-form-container" data-urun-form
         data-mode="duzelt"
         data-action="<?= esc($_SERVER['PHP_SELF'] . '?id=' . $id) ?>"
         data-max-ek-resim="5"
         data-resim-izinli="<?= esc(json_encode(resim_izinli_uzantilar())) ?>"
         data-resim-limit="<?= (int) resim_yukleme_siniri() ?>"
         data-resim-kural="<?= esc(resim_kural_metni()) ?>"
         data-mevcut-ek-resim="<?= (int) $mevcutEkResimSayisi ?>"
         data-kalan-ek-resim="<?= (int) $kalanEkResimHakki ?>">
        <!-- Sol Taraf - Resim Yükleme Alanı -->
        <div class="upload-section">
            <div class="upload-card">
                <h2 class="upload-title">Resim Yönetimi</h2>
                
                <!-- Mevcut Ana Resim -->
                <div class="form-group">
                    <label class="form-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-image"></use></svg>
                        Mevcut Ana Resim
                    </label>
                    <div class="current-images-container">
                        <?php if ($mevcutResim && file_exists("../../content/images/" . $mevcutResim)): ?>
                            <div class="current-image-item">
                                <img src="../../content/images/<?= esc($mevcutResim) ?>" 
                                     alt="<?= esc($urunadi) ?>" 
                                     <?= resim_boyut($mevcutResim) ?> loading="lazy" decoding="async"
                                     class="current-image">
                                <div class="current-image-info">Mevcut Resim</div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" style="margin: 0; text-align: center;">
                                <svg class="ik" aria-hidden="true"><use href="#lk-info"></use></svg>
                                Henüz ana resim yüklenmemiş
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Yeni Ana Resim Yükleme -->
                <div class="form-group">
                    <label class="form-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg>
                        Yeni Ana Resim
                    </label>
                    <div class="file-input-wrapper">
                        <div class="custom-file-label">
                            <svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg>
                            Yeni Ana Resim Seç
                        </div>
                        <input type="file" name="resim" id="resim" accept="<?= resim_accept_ozelligi() ?>" />
                    </div>
                    <div id="imagePreview" class="image-preview-container" style="display:none;">
                        <span class="image-preview-title">Önizleme:</span>
                        <img id="preview" src="#" alt="Resim önizleme" class="image-preview">
                    </div>
                </div>
                
                <!-- Mevcut Ek Resimler -->
                <div class="form-group">
                    <div class="panel">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <svg class="ik" aria-hidden="true"><use href="#lk-images"></use></svg>
                                Mevcut Ek Resimler
                                <span class="badge bg-primary"><?= $mevcutEkResimSayisi ?>/5</span>
                            </h4>
                        </div>
                        <div class="panel-body">
                            <?php if ($mevcutEkResimSayisi > 0): ?>
                                <div class="current-images-grid" id="mevcutEkResimlerGrid">
                                    <?php foreach ($ekResimler as $ekResim): ?>
                                        <div class="current-image-item" id="ekResim-<?= (int)$ekResim['id'] ?>">
                                            <?php if (file_exists("../../content/images/" . $ekResim['resim_yolu'])): ?>
                                                <button type="button" class="delete-image-btn silEkResimBtn" 
                                                        data-resim-id="<?= (int)$ekResim['id'] ?>" 
                                                        data-urun-id="<?= $id ?>"
                                                        title="Bu resmi sil">
                                                    <svg class="ik" aria-hidden="true"><use href="#lk-trash-2"></use></svg>
                                                </button>
                                                <img src="../../content/images/<?= esc($ekResim['resim_yolu']) ?>" 
                                                     alt="Ek Resim" 
                                                     <?= resim_boyut($ekResim['resim_yolu']) ?> loading="lazy" decoding="async"
                                                     class="current-image">
                                                <div class="current-image-info">
                                                    <?php 
                                                    $fileName = $ekResim['resim_yolu'];
                                                    if (strlen($fileName) > 15) {
                                                        $fileName = substr($fileName, 0, 12) . '...';
                                                    }
                                                    echo esc($fileName);
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="delete-image-btn silEkResimBtn" 
                                                        data-resim-id="<?= (int)$ekResim['id'] ?>" 
                                                        data-urun-id="<?= $id ?>"
                                                        title="Bu resmi sil">
                                                    <svg class="ik" aria-hidden="true"><use href="#lk-trash-2"></use></svg>
                                                </button>
                                                <div style="width:100%; height:60px; background: var(--ad-zemin); display:flex; align-items:center; justify-content:center;">
                                                    <span class="text-danger" style="font-size:0.7rem;">Resim yok</span>
                                                </div>
                                                <div class="current-image-info">
                                                    <?php 
                                                    $fileName = $ekResim['resim_yolu'];
                                                    if (strlen($fileName) > 15) {
                                                        $fileName = substr($fileName, 0, 12) . '...';
                                                    }
                                                    echo esc($fileName);
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info" style="margin: 0; text-align: center;">
                                    <svg class="ik" aria-hidden="true"><use href="#lk-info"></use></svg>
                                    Henüz ek resim yüklenmemiş
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Yeni Ek Resimler Yükleme -->
                <div class="form-group">
                    <div class="panel">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg>
                                Yeni Ek Resimler Ekle
                                <span id="kalanHakSpan" class="kalan-hak <?= $kalanEkResimHakki > 0 ? 'text-success' : 'text-danger' ?>">
                                    <?php if ($kalanEkResimHakki > 0): ?>
                                        (Kalan ekleme hakkı: <?= $kalanEkResimHakki ?>)
                                    <?php else: ?>
                                        (Maksimum 5 ek resim sınırına ulaşıldı)
                                    <?php endif; ?>
                                </span>
                                <span id="selectedCount" class="selected-count" style="display: none;">0</span>
                            </h4>
                        </div>
                        <div class="panel-body">
                            <?php if ($kalanEkResimHakki > 0): ?>
                                <div class="file-input-wrapper">
                                    <div class="custom-file-label" id="ekResimLabel">
                                        <svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg>
                                        Yeni Ek Resimleri Seç (Çoklu Seçim)
                                    </div>
                                    <input type="file" name="ek_resimler[]" id="ek_resimler" accept="<?= resim_accept_ozelligi() ?>" multiple>
                                </div>
                            <?php else: ?>
                                <div class="file-input-wrapper">
                                    <div class="custom-file-label" style="background: var(--ad-zemin); color: var(--ad-metin-soluk); cursor: not-allowed;">
                                        <svg class="ik" aria-hidden="true"><use href="#lk-ban"></use></svg>
                                        Yeni Ek Resim Eklenemez
                                    </div>
                                    <input type="file" name="ek_resimler[]" id="ek_resimler" accept="<?= resim_accept_ozelligi() ?>" multiple disabled>
                                </div>
                                <div class="alert alert-warning mt-3">
                                    <svg class="ik" aria-hidden="true"><use href="#lk-alert-triangle"></use></svg>
                                    Maksimum 5 ek resim sınırına ulaşıldı. Yeni resim eklemek için mevcut resimlerden bazılarını silin.
                                </div>
                            <?php endif; ?>
                            
                            <div id="ekResimPreview" style="display:none;">
                                <span class="image-preview-title">Yeni Seçilen Resimler:</span>
                                <div class="thumbnail-grid" id="thumbnailGrid"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sağ Taraf - Form Bilgileri -->
        <div class="form-section">
            <div class="form-card">
                <h2 class="product-title-input">
                    <input type='text' name='urunadi' id="urunadi" value="<?= esc($urunadi) ?>" 
                           class='form-control' required placeholder="Ürün adını girin" 
                           style="font-size: inherit; font-weight: inherit; border: none; padding: 0;" />
                </h2>
                
                <!-- Fiyat -->
                <div class="form-group">
                    <label class="form-label required-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                        Fiyat
                    </label>
                    <input type='text' name='fiyat' id="fiyat" value="<?= esc($fiyat) ?>" 
                           class='form-control' required placeholder="Örnek: 99.99" />
                    <small class="help-block">* Zorunlu alan - Sadece sayı ve nokta kullanın</small>
                </div>
                
                <!-- Stok -->
                <div class="form-group">
                    <label class="form-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-boxes"></use></svg>
                        Stok
                    </label>
                    <input type='number' name='stok' id="stok" value="<?= esc((string)$stok) ?>" 
                           class='form-control' min="0" step="1" />
                    <small class="help-block">Sipariş adetleri bu sayıdan düşülür. 0 = satışa kapalı.</small>
                </div>
                
                <!-- Kategori -->
                <div class="form-group">
                    <label class="form-label required-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-tag"></use></svg>
                        Kategori
                    </label>
                    <!-- Secenekler sayfa baslangicinda sorgulandi: $kategoriler -->
                    
                    <div class="category-selection-container">
                        <select name='kategori_id' id="kategori_id" class='form-control' required>
                            <option value="">Kategori Seçin</option>
                            <?php foreach ($kategoriler as $kayit): ?>
                                <option value="<?= (int)$kayit["id"] ?>" <?= (int)$kayit["id"] == $kategori_id ? "selected" : "" ?>>
                                    <?= esc($kayit["kategoriadi"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="categoryDisplay" class="category-display" style="display: <?= $kategori_id ? 'block' : 'none' ?>;">
                            <div class="selected-category-badge">
                                <svg class="ik" aria-hidden="true"><use href="#lk-check-circle"></use></svg>
                                <span id="selectedCategoryName">
                                    <?php 
                                    if ($kategori_id) {
                                        foreach ($kategoriler as $kayit) {
                                            if ($kayit["id"] == $kategori_id) {
                                                echo esc($kayit["kategoriadi"]);
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <small class="help-block">* Zorunlu alan</small>
                </div>
                
                <!-- Açıklama -->
                <div class="form-group">
                    <label class="form-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-file-text"></use></svg>
                        Açıklama
                    </label>
                    <textarea name='aciklama' id="aciklama" class='form-control' 
                              placeholder="Ürün açıklamasını buraya yazın..."><?= esc($aciklama) ?></textarea>
                </div>
                
                <!-- İşlem Butonları -->
                <div class="button-container">
                    <button type="button" id="btnGuncelle" class='btn btn-primary'>
                        <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Güncelle
                    </button>
                    
                    <a href='liste.php' class='btn btn-danger'>
                        <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg> Ürün Listesi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../footer.php"; ?>

<!-- Bootstrap bundle + SweetAlert2 + admin.js footer.php üzerinden yükleniyor (CKEditor sayfaya özel) -->
<?= varlik_js('content/js/urun-form.js') ?>
