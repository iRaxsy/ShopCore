<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Bildirimler flash() ile session'a birakilir; admin/header.php tek noktadan basar.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_dogrula();
        
        // 1) Girdi dogrulamasi - DB'ye ham deger yazilir, ciktilar esc() ile kacirilir
        $hatalar = [];
        $urunadi = temiz_metin($_POST['urunadi'] ?? '');
        $aciklama = temiz_html($_POST['aciklama'] ?? '');
        $fiyat = filter_var($_POST['fiyat'] ?? '', FILTER_VALIDATE_FLOAT);
        // Stok tam sayi; bos/eksi/gayri-muntazam giris 0'a dusurulmez, hata verilir
        $stok = filter_var($_POST['stok'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $kategori_id = (int)($_POST['kategori_id'] ?? 0);
        $giris_tarihi = date('Y-m-d H:i:s');

        if ($urunadi === '') {
            $hatalar[] = 'Ürün adı boş olamaz.';
        }
        if ($fiyat === false || $fiyat < 0) {
            $hatalar[] = 'Fiyat sıfır veya pozitif bir sayı olmalıdır.';
        }
        if ($stok === false) {
            $hatalar[] = 'Stok negatif olmayan bir tam sayı olmalıdır.';
            $stok = 0;
        }
        $kategoriKontrol = $con->prepare("SELECT id FROM kategoriler WHERE id = ? LIMIT 1");
        $kategoriKontrol->execute([$kategori_id]);
        if ($kategori_id <= 0 || !$kategoriKontrol->fetch()) {
            $hatalar[] = 'Lütfen geçerli bir kategori seçin.';
        }

        // 2) Dosyalar ONCE dogrulanip tasinir (resim_yukle: MIME kontrolu + rastgele ad,
        //    istemcinin verdigi dosya adi kullanilmaz). Boylece basarisiz yuklemede
        //    veritabaninda olmayan bir dosyaya isaret eden urun kaydi olusmaz.
        $hedefKlasor = APP_ROOT . '/content/images/';
        $maxEkResimSayisi = 5;
        $anaResim = '';
        $ekResimYollari = [];
        $tasinanDosyalar = [];

        if (!empty($_FILES['resim']['name'])) {
            $yukleme = resim_yukle($_FILES['resim'], $hedefKlasor);
            if ($yukleme['ok']) {
                $anaResim = $yukleme['ad'];
                // resim_dosyalari(): islenmisse kart varyanti da geri alinir
                $tasinanDosyalar = array_merge($tasinanDosyalar, resim_dosyalari($anaResim));
            } else {
                $hatalar[] = 'Ana resim: ' . $yukleme['hata'];
            }
        }

        if (!empty($_FILES['ek_resimler']['name'][0])) {
            foreach (array_keys($_FILES['ek_resimler']['name']) as $i) {
                if (count($ekResimYollari) >= $maxEkResimSayisi) {
                    $hatalar[] = "Maksimum $maxEkResimSayisi adet ek resim yükleyebilirsiniz; fazlası alınmadı.";
                    break;
                }
                if ($_FILES['ek_resimler']['error'][$i] !== UPLOAD_ERR_OK) {
                    $hatalar[] = esc($_FILES['ek_resimler']['name'][$i]) . ' - dosya yüklenemedi.';
                    continue;
                }
                $yukleme = resim_yukle([
                    'name' => $_FILES['ek_resimler']['name'][$i],
                    'type' => $_FILES['ek_resimler']['type'][$i],
                    'tmp_name' => $_FILES['ek_resimler']['tmp_name'][$i],
                    'error' => $_FILES['ek_resimler']['error'][$i],
                    'size' => $_FILES['ek_resimler']['size'][$i],
                ], $hedefKlasor);

                if ($yukleme['ok']) {
                    $ekResimYollari[] = $yukleme['ad'];
                    $tasinanDosyalar  = array_merge($tasinanDosyalar, resim_dosyalari($yukleme['ad']));
                } else {
                    $hatalar[] = esc($_FILES['ek_resimler']['name'][$i]) . ' - ' . $yukleme['hata'];
                }
            }
        }

        // 3) Hata varsa kayit HIC yazilmaz, tasinan dosyalar geri alinir
        if ($hatalar) {
            foreach ($tasinanDosyalar as $dosya) {
                if (is_file($dosya)) {
                    @unlink($dosya);
                }
            }
            // birden fazla yukleme hatasi tek uyarida toplanir
            flash('uyari', implode(' | ', $hatalar));
        } else {
            // 4) Urun + ek resimler tek islemde: yari yazma durumunda hicbir sey kalmaz
            try {
                $con->beginTransaction();

                $stmt = $con->prepare("INSERT INTO urunler SET urunadi=?, aciklama=?, fiyat=?, stok=?, giris_tarihi=?, resim=?, kategori_id=?");
                $stmt->execute([$urunadi, $aciklama, $fiyat, $stok, $giris_tarihi, $anaResim, $kategori_id]);
                $sonEklenenId = (int)$con->lastInsertId();

                $ekResimStmt = $con->prepare("INSERT INTO urun_resimleri (urun_id, resim_yolu) VALUES (?, ?)");
                foreach ($ekResimYollari as $resimAdi) {
                    $ekResimStmt->execute([$sonEklenenId, $resimAdi]);
                }

                $con->commit();

                if ($ekResimYollari) {
                    flash('basari', 'Ürün başarıyla kaydedildi. ' . count($ekResimYollari) . ' adet ek resim yüklendi.');
                } else {
                    flash('basari', 'Ürün başarıyla kaydedildi.');
                }
            } catch (PDOException $e) {
                if ($con->inTransaction()) {
                    $con->rollBack();
                }
                foreach ($tasinanDosyalar as $dosya) {
                    if (is_file($dosya)) {
                        @unlink($dosya);
                    }
                }
                error_log('urun/ekle: ' . $e->getMessage());
                flash('hata', 'Ürün kaydedilemedi. Lütfen tekrar deneyin.');
            }
        }
    }

    // Formun kategori secenekleri - sorgu cikti baslamadan once calisir
    $kategoriler = $con->query("SELECT id, kategoriadi FROM kategoriler ORDER BY kategoriadi")->fetchAll(PDO::FETCH_ASSOC);

    include "../header.php";
    ?>


<!-- CKEditor 5 (yerel: content/vendor/ckeditor/) -->
<?= ckeditor() ?>

<div class="container">
    <div class="page-header">
        <h1>Ürün Ekle</h1>
    </div>
    
    <div class="product-form-container" data-urun-form
         data-mode="ekle"
         data-action="<?= esc($_SERVER['PHP_SELF']) ?>"
         data-max-ek-resim="5"
         data-resim-izinli="<?= esc(json_encode(resim_izinli_uzantilar())) ?>"
         data-resim-limit="<?= (int) resim_yukleme_siniri() ?>"
         data-resim-kural="<?= esc(resim_kural_metni()) ?>">
        <!-- Sol Taraf - Resim Yükleme Alanı -->
        <div class="upload-section">
            <div class="upload-card">
                <h2 class="upload-title">Resim Yükle</h2>
                
                <!-- Ana Resim Yükleme -->
                <div class="form-group">
                    <label class="form-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-image"></use></svg>
                        Ana Resim
                    </label>
                    <div class="file-input-wrapper">
                        <div class="custom-file-label">
                            <svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg>
                            Ana Resim Seç
                        </div>
                        <input type="file" name="resim" id="resim" accept="<?= resim_accept_ozelligi() ?>" />
                    </div>
                    <div id="imagePreview" class="image-preview-container" style="display:none;">
                        <span class="image-preview-title">Önizleme:</span>
                        <img id="preview" src="#" alt="Resim önizleme" class="image-preview">
                    </div>
                </div>
                
                <!-- Ek Resimler Yükleme -->
                <div class="form-group">
                    <div class="panel">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <svg class="ik" aria-hidden="true"><use href="#lk-images"></use></svg>
                                Ek Resimler
                                <small class="text-success">(Maksimum 5 ek resim)</small>
                                <span id="selectedCount" class="selected-count" style="display: none;">0</span>
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="file-input-wrapper">
                                <div class="custom-file-label" id="ekResimLabel">
                                    <svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg>
                                    Ek Resimleri Seç (Çoklu Seçim)
                                </div>
                                <input type="file" name="ek_resimler[]" id="ek_resimler" accept="<?= resim_accept_ozelligi() ?>" multiple>
                            </div>
                            <div id="ekResimPreview" style="display:none;">
                                <span class="image-preview-title">Seçilen Resimler:</span>
                                <div class="thumbnail-grid" id="thumbnailGrid"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resim Bilgileri -->
                <div class="form-group">
                    <div class="alert alert-info">
                        <svg class="ik" aria-hidden="true"><use href="#lk-info"></use></svg>
                        <strong>Resim Yükleme İpuçları:</strong>
                        <ul style="margin: 10px 0 0 20px; padding: 0;">
                            <li>En iyi görüntü için 500x500 piksel boyutunda resimler kullanın</li>
                            <li>JPG formatı en iyi kaliteyi sağlar</li>
                            <li>PNG formatı şeffaf arkaplan için idealdir</li>
                            <li>GIF formatı animasyonlar için kullanılır</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sağ Taraf - Form Bilgileri -->
        <div class="form-section">
            <div class="form-card">
                <h2 class="product-title-input">
                    <input type='text' name='urunadi' class='form-control' id="urunadi" required 
                           placeholder="Ürün adını girin" style="font-size: inherit; font-weight: inherit; border: none; padding: 0;" />
                </h2>
                
                <!-- Fiyat -->
                <div class="form-group">
                    <label class="form-label required-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                        Fiyat
                    </label>
                    <input type='text' name='fiyat' class='form-control' id="fiyat" required 
                           placeholder="Örnek: 99.99" />
                    <small class="help-block">* Zorunlu alan - Sadece sayı ve nokta kullanın</small>
                </div>
                
                <!-- Stok -->
                <div class="form-group">
                    <label class="form-label">
                        <svg class="ik" aria-hidden="true"><use href="#lk-boxes"></use></svg>
                        Stok
                    </label>
                    <input type='number' name='stok' class='form-control' id="stok" min="0" step="1"
                           value="<?= (int)($_POST['stok'] ?? 100) ?>" />
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
                        <select name='kategori_id' class='form-control' id="kategori_id" required>
                            <option value="">Kategori Seçin</option>
                            <?php foreach ($kategoriler as $kayit): ?>
                                <option value="<?= (int)$kayit["id"] ?>"><?= esc($kayit["kategoriadi"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="categoryDisplay" class="category-display" style="display: none;">
                            <div class="selected-category-badge">
                                <svg class="ik" aria-hidden="true"><use href="#lk-check-circle"></use></svg>
                                <span id="selectedCategoryName"></span>
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
                    <textarea name='aciklama' class='form-control' id="aciklama" 
                              placeholder="Ürün açıklamasını buraya yazın..."></textarea>
                    <small class="help-block">Zengin metin editörü kullanabilirsiniz</small>
                </div>
                
                <!-- İşlem Butonları -->
                <div class="button-container">
                    <button type="button" id="btnKaydet" class='btn btn-primary'>
                        <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Kaydet
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
