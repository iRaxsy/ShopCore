<?php include "header.php"; ?>
    
<div id="carouselExampleIndicators" class="carousel slide slide-alani" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>
    <div class="carousel-inner">
        <!-- Slayt zeminleri gorsel dosyasi degil, CSS gradyan/desen (style.css/.slide-*) -->
        <div class="carousel-item active slide-canta" aria-roledescription="slide" aria-label="Çantalar">
            <div class="carousel-caption">
                <h3>Bayan Çanta Koleksiyonumuz</h3>
                <p>Yeni koleksiyon bayan çantalarında %50'ye varan indirimler</p>
            </div>
        </div>
        <div class="carousel-item slide-saat" aria-roledescription="slide" aria-label="Saatler">
            <div class="carousel-caption text-warning text-end">
                <h3>Saat Koleksiyonumuz</h3>
                <p>Yeni ve modern duvar saatlerinde %20'ye varan indirimler</p>
            </div>
        </div>
        <div class="carousel-item slide-lamba" aria-roledescription="slide" aria-label="Lambalar">
            <div class="carousel-caption text-dark text-start">
                <h3>Lamba Koleksiyonumuz</h3>
                <p>En iyi lambalar en iyi fiyata</p>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Önceki</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Sonraki</span>
    </button>
</div>

<div class="container pt-4">
    <h1 class="text-center baslik">ShopCore'a Hoş Geldiniz</h1>
    <p class="text-center pt-4">
        ShopCore olarak ilk önceliğimiz olan "müşteri memnuniyeti" ve ardından gelen kalite anlayışımızla, satışlarımızı hız kesmeden sürdürmeye devam ederken, sektörün ihtiyaçları doğrultusunda, kaliteli ürün tedarik etmek ve ürünlerimizden daha uzun yıllar faydalanmanız için çalışıyoruz. Türkiye'de değer kazanan bir marka olarak, uluslararası bir marka olma yolunda hızla ilerliyoruz. Kuruluşumuzun 10. yılını kutladığımız bu günlerde, heyecanı ve mutluluğu yaşıyoruz.
    </p>
    
    <div class="row pt-4 bg-body-tertiary p-3">
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="row">
                <div class="col-4 col-md-4 text-center">
                    <span class="ikon-kutu ikon-kutu-lg" aria-hidden="true">
                        <svg class="ik" aria-hidden="true"><use href="#lk-rotate-ccw"></use></svg>
                    </span>
                </div>
                <div class="col-8 col-md-8">
                    <h5>Sorunsuz İade</h5>
                    <p class="mb-0">Bütün ürünlerde 7 gün içinde ücretsiz iade</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="row">
                <div class="col-4 col-md-4 text-center">
                    <span class="ikon-kutu ikon-kutu-lg" aria-hidden="true">
                        <svg class="ik" aria-hidden="true"><use href="#lk-credit-card"></use></svg>
                    </span>
                </div>
                <div class="col-8 col-md-8">
                    <h5>Güvenli Ödeme</h5>
                    <p class="mb-0">Ödemelerinizi tüm banka ve kredi kartlarıyla yapabilirsiniz</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="row">
                <div class="col-4 col-md-4 text-center">
                    <span class="ikon-kutu ikon-kutu-lg" aria-hidden="true">
                        <svg class="ik" aria-hidden="true"><use href="#lk-truck"></use></svg>
                    </span>
                </div>
                <div class="col-8 col-md-8">
                    <h5>Ücretsiz Kargo</h5>
                    <p class="mb-0"><?= kargo_esigi() ?>&#8378; üzeri alışverişlerinizde yurt içi ücretsiz kargo</p>
                </div>
            </div>
        </div>
    </div>
    
    <h1 class="text-center baslik pt-4 pb-3">Yeni Ürünler</h1>
    <div class="row urun-izgara">
        <?php
        $sorgu = 'SELECT id, urunadi, aciklama, fiyat, resim FROM urunler ORDER BY giris_tarihi DESC LIMIT 0,6';
        $stmt = $con->prepare($sorgu);
        $stmt->execute();
        $veri = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($veri as $kayit) { 
        ?>
        <div class="col-6 col-md-4 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <a href="<?= url('urundetay.php', ['id' => (int)$kayit['id']]) ?>">
                    <img class="card-img-top img-fluid urun-kart-gorsel urun-kart-gorsel-lg" src="<?= esc(resim_yolu($kayit['resim'], RESIM_KART_GENISLIK)) ?>" <?= resim_boyut($kayit['resim'], RESIM_KART_GENISLIK) ?> loading="lazy" decoding="async" alt="<?= esc($kayit['urunadi']) ?>">
                </a>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?= esc($kayit['urunadi']) ?></h5>
                    <p class="card-text flex-grow-1"><?= esc(ozetle($kayit['aciklama'], 100)) ?></p>
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center urun-kart-alt">
                            <button class="btn btn-outline-success btn-sm sepete_ekle" 
                                    type="button" 
                                    id="<?= esc($kayit['id']) ?>">
                                <svg class="ik me-1" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg>Sepete Ekle
                            </button>
                            <span class="badge bg-success p-2"><?= esc(para($kayit['fiyat'])) ?>&#8378;</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<?php include "footer.php"; ?>
