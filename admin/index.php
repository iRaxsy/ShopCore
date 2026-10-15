<?php
require_once __DIR__ . '/../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// ---- Panel sayilari: mantik, cikti baslamadan once ----
// Siparis kartinin uclari (adet / ciro / bekleyen) TEK sorguda toplanir;
// sayfa acilisinda N+1 sorgu uretilmez.
try {
    $siparis = $con->query(
        "SELECT COUNT(*) AS adet,
                COALESCE(SUM(toplam), 0) AS ciro,
                COALESCE(SUM(CASE WHEN durum = 'alindi' THEN 1 ELSE 0 END), 0) AS bekleyen,
                COALESCE(SUM(CASE WHEN durum IN ('alindi', 'hazirlaniyor', 'kargoda') THEN toplam ELSE 0 END), 0) AS bekleyen_ciro,
                MAX(olusturma_tarihi) AS son_tarih
         FROM siparisler"
    )->fetch(PDO::FETCH_ASSOC);

    $katalog = $con->query(
        "SELECT (SELECT COUNT(*) FROM urunler) AS urun,
                (SELECT COUNT(*) FROM urunler WHERE stok <= 0) AS tukendi,
                (SELECT COUNT(*) FROM urunler WHERE stok > 0 AND stok <= 5) AS az_stok"
    )->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('admin/index: ' . $e->getMessage());
    admin_hata('HATA: Panel sayıları yüklenemedi.', $e->getMessage(), 500);
}

$son_siparis = (!empty($siparis['son_tarih']))
    ? date('d.m.Y H:i', strtotime($siparis['son_tarih']))
    : '—';

include "header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Yönetim Paneli</h1>
    </div>

    <div class="panel-kartlar">
        <div class="panel-kart panel-kart-siparis">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-receipt"></use></svg>
                </span>
                <span class="panel-kart-baslik">Toplam Sipariş</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$siparis['adet'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">Son sipariş: <?= esc($son_siparis) ?></div>
        </div>

        <div class="panel-kart panel-kart-ciro">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                </span>
                <span class="panel-kart-baslik">Toplam Ciro</span>
            </div>
            <div class="panel-kart-deger"><?= para($siparis['ciro']) ?>&#8378;</div>
            <div class="panel-kart-alt">
                Tüm sipariş kayıtlarının toplamı — iptal edilenler de dahil.
            </div>
        </div>

        <div class="panel-kart panel-kart-bekleyen">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-truck"></use></svg>
                </span>
                <span class="panel-kart-baslik">Bekleyen Sipariş</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$siparis['bekleyen'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">
                "Alındı" durumunda, hazırlanmayı bekleyen
                <strong><?= para($siparis['bekleyen_ciro']) ?>&#8378;</strong> tutarında iş.
            </div>
        </div>

        <div class="panel-kart panel-kart-stok">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-boxes"></use></svg>
                </span>
                <span class="panel-kart-baslik">Ürün / Stok</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$katalog['urun'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">
                <?php if ((int)$katalog['tukendi'] > 0 || (int)$katalog['az_stok'] > 0): ?>
                    <a class="text-lacivert" href="<?= APP_BASE_URL ?>admin/urun/liste.php">
                        <svg class="ik" aria-hidden="true"><use href="#lk-alert-triangle"></use></svg>
                        <?= (int)$katalog['tukendi'] ?> tükenmiş, <?= (int)$katalog['az_stok'] ?> stokta az
                    </a>
                <?php else: ?>
                    Stok durumu temiz: tükenen ürün yok.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="project-container">
        <div class="page-header">
            <h2 class="project-header">ShopCore</h2>
        </div>
        
        <div class="project-content">
            <p>
                ShopCore, ürün ve kategori yönetimini tek panelden yapan
                <span class="project-highlight">PHP 8 + PDO / MySQL</span> tabanlı bir e-ticaret yönetim
                panelidir. Formlar <span class="project-highlight">CSRF korumalı</span>, liste ekranları
                <span class="project-highlight">sayfalı ve toplu işlemlere açık</span> şekilde çalışır.
            </p>

            <p>
                Bu panelden ürün ekleme / güncelleme / silme, kategori atama ve
                <span class="project-highlight">resim galerisi (resim hakkı takibi)</span> yönetilir;
                kullanıcı hesapları buradan oluşturulur. Siparişler ise
                <span class="project-highlight">veritabanına kaydedilir</span>, stok düşümü işlem
                <span class="project-highlight">transaction</span> içinde yapılır ve panelden izlenir.
            </p>
        </div>
        
        <div class="text-center">
            <a class="project-btn"
               href="<?= APP_BASE_URL ?>index.php"
               target="_blank" rel="noopener">
                <svg class="ik" aria-hidden="true"><use href="#lk-external-link"></use></svg> Siteyi Görüntüle &raquo;
            </a>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
