<?php
/**
 * Site uyesinin siparisleri (salt okunur).
 *
 * admin/kullanici/liste.php'deki simge-butondan acilir (?id=N). Liste yalnizca
 * siparisler.kullanici_id = N eslesmeleriyle kurulur; e-posta uzerinden metin
 * aramasi bilincli olarak yapilmaz, cunku ayni e-postayla verilmis misafir
 * siparisleri baska bir uyenin gecmisi sayilmaz. Hesap silindiginde SET NULL ile
 * misafire donen siparisler de burada listelenmez (onlar admin/siparis/liste.php
 * uzerinden gorulur). Durum degistirme bu sayfada YOK: admin/siparis/liste.php
 * ve admin/siparis/detay.php kalibi korunur.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik (sorgular) cikti baslamadan ONCE calisir
$id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    admin_hata('HATA: Geçersiz kullanıcı kimliği.', '', 400);
}

$sayfa = (isset($_GET['sayfa']) && is_numeric($_GET['sayfa'])) ? max(1, (int)$_GET['sayfa']) : 1;
$sayfa_kayit_sayisi = 10;
$ilk_kayit_no = ($sayfa - 1) * $sayfa_kayit_sayisi;

$kullanici = [];
$siparisler = [];
$kayit_sayisi = 0;
$toplam_sayfa = 0;
$ozet = ['adet' => 0, 'ciro' => 0, 'bekleyen' => 0, 'son_tarih' => null];

try {
    $uy = $con->prepare('SELECT id, adsoyad, eposta, telefon, il, ilce, olusturma_tarihi FROM kullanicilar WHERE id = ? LIMIT 1');
    $uy->execute([$id]);
    $kullanici = $uy->fetch(PDO::FETCH_ASSOC);
    if (!$kullanici) {
        // Uye yoksa (orn. hesap silinmis) listelemenin konusu kalmadi
        admin_hata('HATA: Kullanıcı bulunamadı.', '', 404);
    }

    // Kalem sayisi ve toplam adet tek sorguda (N+1 yok) - admin/siparis/liste.php kalibi
    $stmt = $con->prepare("SELECT s.*, COUNT(k.id) AS kalem_sayisi, COALESCE(SUM(k.adet), 0) AS adet_toplam
                           FROM siparisler s
                           LEFT JOIN siparis_kalemleri k ON k.siparis_id = s.id
                           WHERE s.kullanici_id = :uid
                           GROUP BY s.id
                           ORDER BY s.id DESC
                           LIMIT :ilk_kayit_no, :sayfa_kayit_sayisi");
    $stmt->bindValue(':uid', $id, PDO::PARAM_INT);
    $stmt->bindParam(':ilk_kayit_no', $ilk_kayit_no, PDO::PARAM_INT);
    $stmt->bindParam(':sayfa_kayit_sayisi', $sayfa_kayit_sayisi, PDO::PARAM_INT);
    $stmt->execute();
    $siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $toplam_kayit = $con->prepare('SELECT COUNT(*) FROM siparisler WHERE kullanici_id = ?');
    $toplam_kayit->execute([$id]);
    $kayit_sayisi = (int)$toplam_kayit->fetchColumn();
    $toplam_sayfa = (int)ceil($kayit_sayisi / $sayfa_kayit_sayisi);

    // Ust kartlar. "Bekleyen" tanimi admin/index.php ve admin/siparis/liste.php
    // icindeki durum kumesiyle birebir ayni; iki sayfa farkli rakam gostermez.
    $ozet_stmt = $con->prepare("SELECT COUNT(*) AS adet, COALESCE(SUM(toplam), 0) AS ciro,
                                       COALESCE(SUM(CASE WHEN durum IN ('alindi', 'hazirlaniyor', 'kargoda') THEN 1 ELSE 0 END), 0) AS bekleyen,
                                       MAX(olusturma_tarihi) AS son_tarih
                                FROM siparisler WHERE kullanici_id = ?");
    $ozet_stmt->execute([$id]);
    $ozet = $ozet_stmt->fetch(PDO::FETCH_ASSOC) ?: $ozet;
} catch (PDOException $e) {
    admin_hata(db_hata_mesaji($e, 'Veritabanı hatası: İşlem sırasında bir sorun oluştu.'), $e->getMessage(), 500);
}

$son_tarih = ($ozet['son_tarih'] !== null && $ozet['son_tarih'] !== '')
    ? date('d.m.Y H:i', strtotime((string)$ozet['son_tarih']))
    : '';

// Kimlik satiri: bos alanlar parca olarak eklenmez, boylece "· ·" artigi kalmaz
$il_ilce = trim($kullanici['il'] . ($kullanici['ilce'] !== '' ? ' / ' . $kullanici['ilce'] : ''));
$kimlik_parcalar = [(string)$kullanici['eposta']];
if ($kullanici['telefon'] !== '') {
    $kimlik_parcalar[] = (string)$kullanici['telefon'];
}
if ($il_ilce !== '') {
    $kimlik_parcalar[] = $il_ilce;
}
$kimlik_parcalar[] = 'Kayıt: ' . date('d.m.Y', strtotime((string)$kullanici['olusturma_tarihi']));

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Siparişler</h1>
    </div>

    <p class="text-muted mb-3">
        <strong><?= esc($kullanici['adsoyad']) ?></strong> · <?= esc(implode(' · ', $kimlik_parcalar)) ?>
    </p>

    <div class="main-actions-container">
        <div class="left-buttons">
            <a href="liste.php" class="btn btn-outline-secondary">
                <svg class="ik" aria-hidden="true"><use href="#lk-arrow-left"></use></svg> Kullanıcı Listesi
            </a>
            <a href="../siparis/liste.php" class="btn btn-outline-secondary">
                <svg class="ik" aria-hidden="true"><use href="#lk-list"></use></svg> Tüm Siparişler
            </a>
        </div>
    </div>

    <!-- Uye ozeti: Katman 2'deki panel-kart bileseninin kompakt varyanti
         (admin/siparis/liste.php ile ayni bilesen), yeni CSS gerekmez. -->
    <div class="panel-kartlar panel-kartlar-kompakt">
        <div class="panel-kart panel-kart-siparis panel-kart-kompakt">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-receipt"></use></svg>
                </span>
                <span class="panel-kart-baslik">Sipariş</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$ozet['adet'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">
                <?= $son_tarih !== '' ? 'Son sipariş: ' . esc($son_tarih) : 'Hiç sipariş yok' ?>
            </div>
        </div>

        <div class="panel-kart panel-kart-ciro panel-kart-kompakt">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                </span>
                <span class="panel-kart-baslik">Toplam Tutar</span>
            </div>
            <div class="panel-kart-deger"><?= para($ozet['ciro']) ?>&#8378;</div>
            <div class="panel-kart-alt">Bu üyenin siparişlerinin toplamı</div>
        </div>

        <div class="panel-kart panel-kart-bekleyen panel-kart-kompakt">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-truck"></use></svg>
                </span>
                <span class="panel-kart-baslik">Bekleyen</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$ozet['bekleyen'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">Bu üyenin işleme bekleyen siparişi</div>
        </div>
    </div>

    <?php if ($siparisler): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sipariş No</th>
                        <th>Tarih</th>
                        <th>Kalem / Adet</th>
                        <th>Ödeme</th>
                        <th>Toplam</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siparisler as $kayit):
                        $durum_gorunum = siparis_durum_gorunum($kayit['durum']);
                    ?>
                    <tr>
                        <td><strong><?= (int)$kayit['id'] ?></strong></td>
                        <td>
                            <a href="../siparis/detay.php?id=<?= (int)$kayit['id'] ?>">
                                <code><?= esc($kayit['numarasi']) ?></code>
                            </a>
                        </td>
                        <td><?= esc(date('d.m.Y H:i', strtotime($kayit['olusturma_tarihi']))) ?></td>
                        <td><?= (int)$kayit['kalem_sayisi'] ?> kalem / <?= (int)$kayit['adet_toplam'] ?> adet</td>
                        <td><?= esc(siparis_odeme_etiketi($kayit['odeme_tipi'])) ?></td>
                        <td class="price-cell"><strong><?= para($kayit['toplam']) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= esc($durum_gorunum['sinif']) ?>"><?= esc($durum_gorunum['etiket']) ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="../siparis/detay.php?id=<?= (int)$kayit['id'] ?>" class="btn btn-info"
                                   title="Sipariş detayı" aria-label="Sipariş detayı">
                                    <svg class="ik" aria-hidden="true"><use href="#lk-eye"></use></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_sayfalama($sayfa, $toplam_sayfa, 'siparisler.php', ['id' => $id]) ?>

    <?php else: ?>
        <div class="alert alert-warning text-center">
            <svg class="ik" style="font-size: 1.5em; margin-right: 10px;" aria-hidden="true"><use href="#lk-info"></use></svg>
            <strong>Bu kullanıcının henüz siparişi yok.</strong>
        </div>
    <?php endif; ?>
</div>

<?php include "../footer.php"; ?>
