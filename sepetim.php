<?php 
include "header.php";

// Tek kaynak: rozet, sepet tablosu ve odeme sayfasi ayni ozeti okur
$sepetOzeti = sepet_ozeti();

// DB'den silinmis urunler sepetten dusulur; bayl id'ler toplama katilmaz
if ($sepetOzeti['urun_sayisi'] !== count($_SESSION['sepet'] ?? [])) {
    $gecerliIdler = array_column($sepetOzeti['kalemler'], 'id');
    foreach (array_keys($_SESSION['sepet'] ?? []) as $sepetId) {
        if (!in_array((int)$sepetId, $gecerliIdler, true)) {
            unset($_SESSION['sepet'][$sepetId]);
        }
    }
}

if (!$sepetOzeti['kalemler']) {
?>
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-warning text-center py-5">
                <svg class="ik ik-4x mb-4 text-muted" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg>
                <h3>Sepetiniz Boş</h3>
                <p class="mb-4">Sepetinizde henüz ürün bulunmamaktadır.</p>
                <a href="urunler.php" class="btn btn-primary btn-lg">
                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-shopping-bag"></use></svg>Alışverişe Başla
                </a>
            </div>
        </div>
    </div>
</div>
<?php
}
else {
    $urun_toplami = $sepetOzeti['ara_toplam'];
?>
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="baslik mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-shopping-cart"></use></svg>Sepetim</h2>
        <a href="urunler.php" class="btn btn-outline-primary">
            <svg class="ik me-2" aria-hidden="true"><use href="#lk-plus"></use></svg>Alışverişe Devam Et
        </a>
    </div>
    
    <!-- Sepetteki ürünleri görüntüleyen HTML tablosu -->
    <div class="table-responsive">
        <table class="table table-hover sepet-tablo">
            <thead>
                <tr>
                    <th class="text-center">Ürün</th>
                    <th>Ürün Adı</th>
                    <th class="text-center">Birim Fiyat</th>
                    <th class="text-center">Adet</th>
                    <th class="text-center">Toplam</th>
                    <th class="text-center">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($sepetOzeti['kalemler'] as $kalem) {
                    $id       = $kalem['id'];
                    $urunadi  = $kalem['urunadi'];
                    $fiyat    = $kalem['birim_fiyat'];
                    $adet     = $kalem['adet'];
                    $urun_top = $kalem['satir_toplam'];
                    
                    $kullanilacakResim = resim_yolu($kalem['resim'], RESIM_KART_GENISLIK);
                    $kullanilacakBoyut = resim_boyut($kalem['resim'], RESIM_KART_GENISLIK);
                ?>
                <tr>
                    <td class="text-center">
                        <img src="<?php echo esc($kullanilacakResim); ?>" <?php echo $kullanilacakBoyut; ?> loading="lazy" decoding="async" class="img-thumbnail" style="width: 80px; height: 80px; object-fit: cover;" alt="<?php echo esc($urunadi); ?>">
                    </td>
                    <td>
                        <h6 class="mb-1">
                            <a href="urundetay.php?id=<?php echo esc($id); ?>" class="text-decoration-none">
                                <?php echo esc($urunadi); ?>
                            </a>
                        </h6>
                    </td>
                    <td class="text-center">
                        <h6 class="mb-0"><?php echo para($fiyat); ?>&#8378;</h6>
                    </td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center">
                            <div class="input-group adet-kutu">
                                <input type="number" value="<?php echo esc($adet); ?>" 
                                       id="urun_<?php echo esc($id); ?>" 
                                       min="1" max="99" 
                                       class="form-control form-control-sm text-center">
                                <button class="btn btn-outline-primary btn-sm urun-guncelle" 
                                        type="button" 
                                        id="<?php echo esc($id); ?>"
                                        title="Adeti Güncelle">
                                    <svg class="ik" aria-hidden="true"><use href="#lk-refresh-cw"></use></svg>
                                </button>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <h6 class="mb-0 fw-bold"><?php echo para($urun_top); ?>&#8378;</h6>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-outline-danger btn-sm urun-sil" 
                                type="button" 
                                id="<?php echo esc($id); ?>"
                                title="Ürünü Sil">
                            <svg class="ik" aria-hidden="true"><use href="#lk-trash-2"></use></svg>
                        </button>
                    </td>
                </tr>
                <?php
                }
                
                $kargo_ucreti = $sepetOzeti['kargo'];
                $toplam = $sepetOzeti['toplam'];
                ?>
            </tbody>
        </table>
    </div><!--/Sepetteki ürünler-->

    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3"><svg class="ik me-2" aria-hidden="true"><use href="#lk-info"></use></svg>Kargo Bilgisi</h5>
                    <div class="alert <?php echo ($urun_toplami >= KARGO_ESIGI) ? 'alert-success' : 'alert-info'; ?>">
                        <?php if($urun_toplami >= KARGO_ESIGI): ?>
                        <svg class="ik me-2" aria-hidden="true"><use href="#lk-check-circle"></use></svg>
                        Tebrikler! <?= kargo_esigi() ?>&#8378; üzeri alışverişinizde kargo ücretsiz.
                        <?php else: ?>
                        <svg class="ik me-2" aria-hidden="true"><use href="#lk-truck"></use></svg>
                        <?= kargo_esigi() ?>&#8378; üzeri alışverişlerde kargo ücretsiz.
                        <?php $kalan = KARGO_ESIGI - $urun_toplami; ?>
                        Ücretsiz kargo için <?= para($kalan) ?>&#8378; daha ekleyin.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mt-4 mt-lg-0">
            <!-- Sepet özeti -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body">
                    <h5 class="mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-receipt"></use></svg>Sipariş Özeti</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td>Toplam Adet</td>
                                <td class="text-end"><?php echo (int)$sepetOzeti['adet_toplam']; ?> adet</td>
                            </tr>
                            <tr>
                                <td>Ürün Tutarı</td>
                                <td class="text-end"><?php echo para($urun_toplami); ?>&#8378;</td>
                            </tr>
                            <tr>
                                <td>Kargo Ücreti</td>
                                <td class="text-end">
                                    <?php if($kargo_ucreti == 0): ?>
                                        <span class="text-success">Ücretsiz</span>
                                    <?php else: ?>
                                        <?php echo para($kargo_ucreti); ?>&#8378;
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold">Toplam</td>
                                <td class="text-end fw-bold fs-5 text-success">
                                    <?php echo para($toplam); ?>&#8378;
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <?php if (kullanici_girisi_var_mi()): ?>
                        <form name="frm_sepet" method="post" action="satinal.php" class="mt-4">
                            <?= csrf_alan() ?>

                            <button type="submit" class="btn btn-success btn-lg w-100 py-3">
                                <svg class="ik me-2" aria-hidden="true"><use href="#lk-credit-card"></use></svg>Satın Al
                            </button>

                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <svg class="ik me-1" aria-hidden="true"><use href="#lk-lock"></use></svg>
                                    Güvenli ödeme ile devam edeceksiniz
                                </small>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Giris yapmayan siparis veremez: satin alma formu yerine
                            giris/kayit cagrisi basilir. Sepet session'da kaldigi icin
                            kullanici giris sonrasi ayni sepete doner. -->
                        <div class="mt-4">
                            <div class="alert alert-info small mb-3" role="alert">
                                <svg class="ik me-2" aria-hidden="true"><use href="#lk-info"></use></svg>
                                Sipariş verebilmek için giriş yapmanız gerekiyor. Sepetiniz korunur.
                            </div>

                            <div class="d-grid gap-2">
                                <a href="<?= url('giris.php', ['geri' => 'sepetim.php']) ?>" class="btn btn-success btn-lg py-3">
                                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-log-in"></use></svg>Giriş yaparak sipariş ver
                                </a>
                                <a href="<?= url('kayit.php', ['geri' => 'sepetim.php']) ?>" class="btn btn-outline-primary">
                                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-circle-user"></use></svg>Hesap oluştur
                                </a>
                            </div>

                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <svg class="ik me-1" aria-hidden="true"><use href="#lk-lock"></use></svg>
                                    Güvenli ödeme ile devam edeceksiniz
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!--/Sepet özeti-->
        </div>
    </div>
</div>
<?php
}
include "footer.php"; 
?>
