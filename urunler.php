<?php include "header.php"; ?>

<!-- Banner kısmı - CSS zemin (style.css/.banner-alani), görsel dosyası kullanılmıyor -->
<div class="container-fluid px-0 mx-0">
    <div class="row g-0">
        <div class="col-12 banner-alani">
            <h2 class="banner-yazi">Ürünler</h2>
            <p class="banner-alt-yazi">İhtiyacın olan her şey tek sayfada</p>
        </div>
    </div>
</div>

<!-- Ana içerik -->
<div class="container-fluid px-0 mx-0">
    <div class="row g-0 justify-content-center">
        <div class="col-12">
            <div class="container mt-4 px-3">
                <div class="row">
                    <!-- Sol sidebar - Filtreler -->
                    <div class="col-lg-3 mb-4">
                        <!-- Dar ekranda filtreler katlanir; 992px ve uzerinde eskisi gibi acik -->
                        <button class="btn btn-outline-success w-100 mb-3 d-lg-none filtre-ac-btn" type="button"
                                data-bs-toggle="collapse" data-bs-target="#filtreAlani"
                                aria-controls="filtreAlani" aria-expanded="false">
                            <svg class="ik me-1" aria-hidden="true"><use href="#lk-sliders-horizontal"></use></svg> Filtreler
                        </button>
                        <div class="collapse d-lg-block" id="filtreAlani">
                        <!-- Kategoriler -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-body">
                                <h4 class="baslik mb-0">Kategoriler</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php
                                    $aranan = isset($_GET["aranan"]) ? (string)$_GET["aranan"] : "";
                                    $kategori = isset($_GET["id"]) ? (string)$_GET["id"] : "";
                                    $siralama = isset($_GET["siralama"]) ? (string)$_GET["siralama"] : "akilli";
                                    $fiyat = isset($_GET["fiyat"]) ? (string)$_GET["fiyat"] : "0";
                                    
                                    $sorgu = 'SELECT kategoriler.*, COUNT(urunler.id) AS adet FROM kategoriler LEFT JOIN urunler ON kategoriler.id = urunler.kategori_id GROUP BY kategoriler.id ORDER BY kategoriler.kategoriadi';
                                    $stmt = $con->prepare($sorgu);
                                    $stmt->execute();
                                    $veri = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $toplam = 0;
                                    
                                    foreach ($veri as $kayit) { 
                                        $aktif = ($kategori == $kayit["id"]) ? "active" : "";
                                        $kat_url = url('urunler.php', [
                                            'id' => (int)$kayit['id'],
                                            'aranan' => $aranan,
                                            'siralama' => $siralama,
                                            'fiyat' => $fiyat,
                                        ]);
                                    ?>
                                    <a href="<?= $kat_url ?>" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $aktif ?>">
                                        <?= esc($kayit["kategoriadi"]) ?>
                                        <span class="badge bg-primary rounded-pill"><?= esc($kayit["adet"]) ?></span>
                                    </a>
                                    <?php 
                                        $toplam += $kayit["adet"]; 
                                    } 
                                    $hepsi_url = url('urunler.php', ['aranan'=>$aranan,'siralama'=>$siralama,'fiyat'=>$fiyat]);
                                    ?>
                                    <a href="<?= $hepsi_url ?>" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($kategori == "") ? "active" : "" ?>">
                                        Hepsi
                                        <span class="badge bg-primary rounded-pill"><?= esc($toplam) ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Sıralama -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-body">
                                <h4 class="baslik mb-0">Sıralama</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $sira_link = function($s) use ($aranan, $kategori, $fiyat) {
                                        return url('urunler.php', ['siralama'=>$s,'aranan'=>$aranan,'id'=>$kategori,'fiyat'=>$fiyat]);
                                    };
                                    ?>
                                    <a href="<?= $sira_link('akilli') ?>" 
                                       class="list-group-item list-group-item-action <?= ($siralama == "akilli") ? "active" : "" ?>">
                                        Akıllı sıralama
                                    </a>
                                    <a href="<?= $sira_link('yeni') ?>" 
                                       class="list-group-item list-group-item-action <?= ($siralama == "yeni") ? "active" : "" ?>">
                                        Yeni ürünler
                                    </a>
                                    <a href="<?= $sira_link('artan') ?>" 
                                       class="list-group-item list-group-item-action <?= ($siralama == "artan") ? "active" : "" ?>">
                                        Artan fiyat
                                    </a>
                                    <a href="<?= $sira_link('azalan') ?>" 
                                       class="list-group-item list-group-item-action <?= ($siralama == "azalan") ? "active" : "" ?>">
                                        Azalan fiyat
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Fiyat Aralığı -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-body">
                                <h4 class="baslik mb-0">Fiyat Aralığı</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $fiyat_link = function($f) use ($aranan, $kategori, $siralama) {
                                        return url('urunler.php', ['fiyat'=>$f,'aranan'=>$aranan,'id'=>$kategori,'siralama'=>$siralama]);
                                    };
                                    $fiyat_araliklari = [
                                        '0' => 'Yok',
                                        '1' => '0 - 25 &#8378;',
                                        '2' => '25 - 50 &#8378;',
                                        '3' => '50 - 100 &#8378;',
                                        '4' => '100 - 250 &#8378;',
                                        '5' => '250 - 500 &#8378;',
                                        '6' => '500 - 1000 &#8378;',
                                    ];
                                    foreach ($fiyat_araliklari as $kod => $etiket): 
                                    ?>
                                    <a href="<?= $fiyat_link($kod) ?>" 
                                       class="list-group-item list-group-item-action <?= ($fiyat == $kod) ? "active" : "" ?>">
                                        <?= $etiket ?>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        </div><!-- /filtreAlani -->
                    </div>

                    <!-- Ürünler -->
                    <div class="col-lg-9">
                        <!-- Arama sonuç bilgisi -->
                        <?php if ($aranan != "") : ?>
                        <div class="alert alert-info mb-4">
                            "<strong><?= esc($aranan) ?></strong>" için arama sonuçları
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        $sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
                        $sayfa = max(1, $sayfa);
                        $sayfa_basi_kayit = 6;
                        $baslangic = ($sayfa - 1) * $sayfa_basi_kayit;
                        
                        switch($siralama){
                            case 'yeni': $orderby = "giris_tarihi desc"; break;
                            case 'artan': $orderby = "fiyat"; break;
                            case 'azalan': $orderby = "fiyat desc"; break;
                            default: $orderby = "dzltm_tarihi desc";
                        }
                        
                        $where1 = "";
                        switch($fiyat){
                            case '1': $where1 = "fiyat between 0 and 25"; break;
                            case '2': $where1 = "fiyat between 25 and 50"; break;
                            case '3': $where1 = "fiyat between 50 and 100"; break;
                            case '4': $where1 = "fiyat between 100 and 250"; break;
                            case '5': $where1 = "fiyat between 250 and 500"; break;
                            case '6': $where1 = "fiyat between 500 and 1000"; break;
                        }

                        // Guvenli sorgu kurma: kullanici girdisi daima bagli parametre
                        $kosullar = [];
                        $params   = [];
                        if ($where1 !== "") {
                            $kosullar[] = "(" . $where1 . ")"; // sabit beyaz-liste, guvenli
                        }
                        if ($kategori !== "") {
                            $kosullar[] = "kategori_id = :kid";
                            $params[':kid'] = (int)$kategori;
                        }
                        $aranan_ham = arama_girdisi($_GET["aranan"] ?? "");
                        if ($aranan_ham !== "") {
                            // kullanicinin yazdigi % ve _ desen karakteri degil, veridir
                            $kosullar[] = "urunadi LIKE :aranan ESCAPE '!'";
                            $params[':aranan'] = like_deseni($aranan_ham);
                        }
                        $where = $kosullar ? implode(" AND ", $kosullar) : "1=1";

                        $sorgu_sayisi = "SELECT COUNT(*) as toplam FROM urunler WHERE $where";
                        $stmt_sayisi = $con->prepare($sorgu_sayisi);
                        $stmt_sayisi->execute($params);
                        $toplam_kayit = $stmt_sayisi->fetch(PDO::FETCH_ASSOC)['toplam'];

                        $toplam_sayfa = ceil($toplam_kayit / $sayfa_basi_kayit);

                        if ($sayfa > $toplam_sayfa && $toplam_sayfa > 0) {
                            $sayfa = $toplam_sayfa;
                            $baslangic = ($sayfa - 1) * $sayfa_basi_kayit;
                        }

                        // $orderby yukarida sabit beyaz-listeden gelir (guvenli)
                        $sorgu = "SELECT id, urunadi, aciklama, fiyat, resim FROM urunler WHERE $where ORDER BY $orderby LIMIT :offset, :limit";
                        $stmt = $con->prepare($sorgu);
                        $stmt->bindValue(':offset', $baslangic, PDO::PARAM_INT);
                        $stmt->bindValue(':limit', $sayfa_basi_kayit, PDO::PARAM_INT);
                        foreach ($params as $k => $v) {
                            $stmt->bindValue($k, $v, PDO::PARAM_STR);
                        }
                        $stmt->execute();
                        $veri = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $urun_sayisi = count($veri);

                        ?>
                        
                        <div class="row g-3 mb-4 urun-izgara">
                            <?php
                            if ($urun_sayisi > 0) {
                                foreach ($veri as $kayit) { 
                            ?>
                            <div class="col-6 col-md-6 col-lg-4">
                                <div class="card h-100 shadow-sm">
                                    <a href="<?= url('urundetay.php', ['id' => (int)$kayit['id']]) ?>">
                                        <img class="card-img-top urun-kart-gorsel" src="<?= esc(resim_yolu($kayit['resim'], RESIM_KART_GENISLIK)) ?>" <?= resim_boyut($kayit['resim'], RESIM_KART_GENISLIK) ?> loading="lazy" decoding="async"
                                             alt="<?= esc($kayit['urunadi']) ?>">
                                    </a>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= esc($kayit['urunadi']) ?></h5>
                                        <p class="card-text flex-grow-1"><?= esc(ozetle($kayit['aciklama'], 100)) ?></p>
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center urun-kart-alt">
                                                <button class="btn btn-outline-success btn-sm sepete_ekle" 
                                                        type="button" 
                                                        id="<?= esc($kayit['id']) ?>">
                                                    <svg class="ik" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg> Sepete Ekle
                                                </button>
                                                <span class="badge bg-success p-2">
                                                    <?= esc(para($kayit['fiyat'])) ?>&#8378;
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                }
                            } else {
                            ?>
                            <div class="col-12">
                                <div class="alert alert-warning text-center py-5">
                                    <svg class="ik ik-3x mb-3" aria-hidden="true"><use href="#lk-alert-circle"></use></svg>
                                    <h3>Ürün bulunamadı</h3>
                                    <p class="mb-0">Seçtiğiniz kriterlere uygun ürün bulunamadı.</p>
                                    <a href="urunler.php" class="btn btn-primary mt-3">Tüm Ürünleri Göster</a>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        
                        <!-- Sayfalandırma Navigasyonu -->
                        <?php
                        echo sayfalama($sayfa, $toplam_sayfa, 'urunler.php', [
                            'id' => $kategori,
                            'aranan' => $aranan,
                            'siralama' => $siralama,
                            'fiyat' => $fiyat,
                        ]);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
